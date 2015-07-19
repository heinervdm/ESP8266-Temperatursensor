#include "ets_sys.h"
#include "osapi.h"
#include "gpio.h"
#include "os_type.h"
#include "user_config.h"
#include "user_interface.h"
#include "ds18x20.h"
#include "onewire.h"
#include "stdout.h"
#include "espmissingincludes.h"
#include "espconn.h"
#include "ip_addr.h"
#include "mem.h"

#define user_procTaskPrio        0
#define user_procTaskQueueLen    1

uint8_t gSensorIDs[MAXSENSORS][OW_ROMCODE_SIZE];
os_event_t    user_procTaskQueue[user_procTaskQueueLen];
static os_timer_t some_timer;

unsigned char *default_certificate;
unsigned int default_certificate_len = 0;
unsigned char *default_private_key;
unsigned int default_private_key_len = 0;

void getHexStr(char *buf, uint8_t *arr, uint8_t size) {
	uint8_t i=0;
	for (;i<size;i++) {
		uint8_t b = (arr[i] >> 4) & 0x0F;
		if (b > 9) {
			b += 'A'-10;
		}
		else {
			b+= '0';
		}
		buf[i*2]=b;
		b = (arr[i]) & 0x0F;
		if (b > 9) {
			b += 'A'-10;
		}
		else {
			b+= '0';
		}
		buf[i*2+1]=b;
	}
	buf[size*2] = '\0';
}

static uint8_t search_sensors(void)
{
	uint8_t i;
	uint8_t id[OW_ROMCODE_SIZE];
	uint8_t diff, nSensors;

	os_printf("Scanning Bus for DS18X20\n");

	ow_reset();

	nSensors = 0;

	diff = OW_SEARCH_FIRST;
	while ( diff != OW_LAST_DEVICE && nSensors < MAXSENSORS ) {
		DS18X20_find_sensor( &diff, &id[0] );

		if( diff == OW_PRESENCE_ERR ) {
			os_printf( "No Sensor found\n" );
			break;
		}

		if( diff == OW_DATA_ERR ) {
			os_printf( "Bus Error\n" );
			break;
		}

		for ( i=0; i < OW_ROMCODE_SIZE; i++ )
			gSensorIDs[nSensors][i] = id[i];

		nSensors++;
	}

	return nSensors;
}

int32_t getTemperature(uint8_t sensor) {
	int32_t temp;

	if ( DS18X20_start_meas( DS18X20_POWER_EXTERN, &gSensorIDs[sensor][0] ) == DS18X20_OK ) {
		os_delay_us( DS18B20_TCONV_12BIT * 1000);
		if ( DS18X20_read_maxres( &gSensorIDs[sensor][0], &temp) != DS18X20_OK ) {
			os_printf("DS18X20: crc error\n");
		}
	}
	else {
		os_printf("DS18X20: failed (short circuit?)");
	}
	char buf[OW_ROMCODE_SIZE*2];
	getHexStr(&buf[0], &gSensorIDs[sensor][0], OW_ROMCODE_SIZE);
	os_printf("Sensor: %d, Temperature: %d, ID: %s\n", sensor, temp, buf);

	return temp;
}

static void ICACHE_FLASH_ATTR disconnect_callback(void * arg) {
	struct espconn *conn = (struct espconn *)arg;
	
	if(conn == NULL) {
		return;
	}

	if(conn->proto.tcp != NULL) {
		os_free(conn->proto.tcp);
	}
	os_free(conn);
// 	system_deep_sleep_set_option(2);
	system_deep_sleep(1000*1000*60*5);
}

static void ICACHE_FLASH_ATTR receive_callback(void * arg, char * buf, unsigned short len) {
	buf[len-1] = 0;
	os_printf("%s", buf);
}

static void ICACHE_FLASH_ATTR sent_callback(void * arg) {
	os_delay_us(1);
}

static void ICACHE_FLASH_ATTR error_callback(void *arg, sint8 errType) {
	disconnect_callback(arg);
}

static void ICACHE_FLASH_ATTR connect_callback(void * arg) {
	struct espconn * conn = (struct espconn *)arg;
	espconn_regist_recvcb(conn, receive_callback);
	espconn_regist_sentcb(conn, sent_callback);
	os_delay_us(1);
// 	struct sensor_reading *r = readDS18B20();
	uint8_t n = search_sensors();
	char buf1[n*50];
	char buf2[50];
	char uid[OW_ROMCODE_SIZE*2+2];
	for (uint8_t i = 0; i < n;i++) {
		int32_t temp = getTemperature(i);
		getHexStr(&uid[0],&gSensorIDs[i][0],OW_ROMCODE_SIZE);
		os_sprintf(buf2,"&value[]=%d.%d&uid[]=%s",temp/10000,temp%10000,uid);
		os_strcat(buf1, buf2);
		os_delay_us(1);
	}
	char macstr[2*6+2];
	uint8_t mac[6];
	wifi_get_macaddr(STATION_IF,&mac[0]);
	getHexStr(&macstr[0],&mac[0],6);
	os_printf("Got MAC addr: %s\n", macstr);
	float v = readvdd33()/1024.;
	os_printf("Got power reading: %d.%d\n", (int)v, (int)((v-(int)v)*100));
	os_delay_us(1);

	char buf[200];
	int len = os_sprintf(buf,
						"GET /index.php?uid[]=%s&value[]=%d.%d%s HTTP/1.1\r\n"
						"Host: " HOSTNAME ":%d\r\n"
						"Connection: close\r\n"
						"User-Agent: ESP8266\r\n"
						EXTRA_HEADER "\r\n",
					macstr, (int)v, (int)((v-(int)v)*100), buf1, PORT);
	espconn_sent(conn, (uint8_t *)buf, len);
}

static void some_timerfunc(void *arg) {
	ip_addr_t addr;
	IPADDR(&addr);

	struct espconn * conn = (struct espconn *)os_malloc(sizeof(struct espconn));
	conn->type = ESPCONN_TCP;
	conn->state = ESPCONN_NONE;
	conn->proto.tcp = (esp_tcp *)os_malloc(sizeof(esp_tcp));
	conn->proto.tcp->local_port = espconn_port();
	conn->proto.tcp->remote_port = 80;
	
	os_memcpy(conn->proto.tcp->remote_ip, &addr, 4);

	espconn_regist_connectcb(conn, connect_callback);
	espconn_regist_disconcb(conn, disconnect_callback);
	espconn_regist_reconcb(conn, error_callback);
	espconn_connect(conn);
}

//Do nothing function
static void ICACHE_FLASH_ATTR user_procTask(os_event_t *events) {
	os_delay_us(10);
}

void wifi_handle_event_cb(System_Event_t *evt) {
	os_printf("event %x\n", evt->event);
	switch (evt->event) {
		case EVENT_STAMODE_CONNECTED:
			os_printf("connect to ssid %s, channel %d\n",
					  evt->event_info.connected.ssid,
			 evt->event_info.connected.channel);
			break;
		case EVENT_STAMODE_DISCONNECTED:
			os_printf("disconnect from ssid %s, reason %d\n",
					  evt->event_info.disconnected.ssid,
			 evt->event_info.disconnected.reason);
			os_timer_disarm(&some_timer);
			break;
		case EVENT_STAMODE_AUTHMODE_CHANGE:
			os_printf("mode: %d -> %d\n",
					  evt->event_info.auth_change.old_mode,
			 evt->event_info.auth_change.new_mode);
			break;
		case EVENT_STAMODE_GOT_IP:
			os_printf("ip:" IPSTR ",mask:" IPSTR ",gw:" IPSTR,
					  IP2STR(&evt->event_info.got_ip.ip),
					  IP2STR(&evt->event_info.got_ip.mask),
					  IP2STR(&evt->event_info.got_ip.gw));
			os_printf("\n");

// 			os_timer_disarm(&some_timer);
// 			os_timer_setfn(&some_timer, (os_timer_func_t *)some_timerfunc, NULL);
// 			os_timer_arm(&some_timer, 1000*60, 1);
			os_delay_us(1);
			some_timerfunc(NULL);
			break;
		default:
			break;
	}
}

//Init function 
void ICACHE_FLASH_ATTR user_init() {
	char ssid[32] = SSID;
	char password[64] = SSID_PASSWORD;
	struct station_config stationConf;
	stdoutInit();
	//Set station mode
	wifi_set_opmode(0x1);
	//Set ap settings
	os_memcpy(&stationConf.ssid, ssid, 32);
	os_memcpy(&stationConf.password, password, 64);
	wifi_station_set_config(&stationConf);

	wifi_set_event_handler_cb(&wifi_handle_event_cb);

	system_os_task(user_procTask, user_procTaskPrio,user_procTaskQueue, user_procTaskQueueLen);
}