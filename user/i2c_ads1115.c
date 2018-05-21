#include "i2c.h"
#include "i2c_ads1115.h"
#include "espmissingincludes.h"

#define ADS1115_READ 1
#define ADS1115_WRITE 0

uint16_t ICACHE_FLASH_ATTR
ADS1115_readRawValue(void) {
	i2c_start();
	i2c_writeByte(ADS1115_ADDRESS & ADS1115_WRITE);
	if(!i2c_check_ack()){
		i2c_stop();
		return(0);
	}
	i2c_writeByte(0x1);// This sets the pointer register so that the following two bytes write to the config register
	if(!i2c_check_ack()){
		i2c_stop();
		return(0);
	}
	i2c_writeByte(0xC3);// This sets the 8 MSBs of the config register (bits 15-8) to 11000011
	if(!i2c_check_ack()){
		i2c_stop();
		return(0);
	}
	i2c_writeByte(0x03);// This sets the 8 LSBs of the config register (bits 7-0) to 00000011
	if(!i2c_check_ack()){
		i2c_stop();
		return(0);
	}

	i2c_writeByte(ADS1115_ADDRESS & ADS1115_READ);
	if(!i2c_check_ack()){
		i2c_stop();
		return(0);
	}
	uint16_t tres = 0;
	while((tres & 0x80) == 0){
		uint8_t msb = i2c_readByte();              
		i2c_send_ack(1);
		uint8_t lsb = i2c_readByte();
		i2c_send_ack(0);
		tres += msb << 8;
	}

	i2c_writeByte(ADS1115_ADDRESS & ADS1115_WRITE);
	if(!i2c_check_ack()){
		i2c_stop();
		return(0);
	}
	i2c_writeByte(0x0);// Set pointer register to 0 to read from the conversion register
	if(!i2c_check_ack()){
		i2c_stop();
		return(0);
	}

	i2c_writeByte(ADS1115_ADDRESS & ADS1115_READ);
	if(!i2c_check_ack()){
		i2c_stop();
		return(0);
	}
	uint8_t msb = i2c_readByte();              
	i2c_send_ack(1);
	uint8_t lsb = i2c_readByte();
	i2c_send_ack(0);
	i2c_stop();
	uint16_t res = msb << 8;
	res += lsb;
	return res;
}

void ICACHE_FLASH_ATTR
ADS1115_init(void) {
	i2c_init();
}
