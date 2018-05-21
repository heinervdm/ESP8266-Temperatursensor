#ifndef __I2C_ADS1115_H
#define	__I2C_ADS1115_H

#include "c_types.h"
#include "ets_sys.h"
#include "osapi.h"

#define ADS1115_ADDRESS   0x48

#define ADS1115_REG_CONVERSION 0x0
#define ADS1115_REG_CONFIG     0x1
#define ADS1115_REG_LOW_THRES  0x2
#define ADS1115_REG_HIGH_THRES 0x3

uint16_t ADS1115_readRawValue(void);
void ADS1115_init(void);

#endif
