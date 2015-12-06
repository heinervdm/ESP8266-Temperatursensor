# ESP8266-Temperatursensor
Wifi temperature sensor based on the Espressif ESP8266 Wifi module and the Dallas DS18X20 temperature sensor

For this project it is assumed, that the ESP8266 is configured for deep sleep. For e.g. ESP-01 one needs to patch to PCB, for ESP-03 one has to close the solder jumper neer pin 8.

To compile you have to adjust the paths in the Makefile and copy user_config.h.template to user_config.h and adjust the defines inside.
I'm using a hardcoded IP address because the DNS was not working very well for me and therefore consumes to much power.

The Firmware will read all connected DS18X20 sensors (A define in user_config.h determines the maximum amount of sensors) and will call a the path: /index.php?value[]=...&uid[]=...&... with the read temperature and the uid of the corresponding sensor, as well as the current supply voltage of the ESP8266 module and it's mac address as uid.

# This is based on the following projects:

DS18X20 readout: http://siwawi.bauing.uni-kl.de/avr_projects/tempsensor/

Makefile & user_main structure: https://github.com/esp8266/source-code-examples

ESP8266 eagle footprint: https://github.com/wvanvlaenderen/ESP8266-Eagle_Library

Javascript plotting: https://github.com/dima117/Chart.Scatter

BMP180 readout: https://github.com/reaper7/esp8266_i2c_bmp180

DHT11/22 readout: https://github.com/mathew-hall/esp8266-dht
