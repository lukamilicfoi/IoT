/* Baisc macros and functions for Bluetooth Host Controller Interface Layer.

   This example code is in the Public Domain (or CC0 licensed, at your option.)

   Unless required by applicable law or agreed to in writing, this
   software is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR
   CONDITIONS OF ANY KIND, either express or implied.
 */

#pragma once

#ifdef __cplusplus
extern "C" {
#endif

#include "stdio.h"
#include "string.h"

#define HCI_H4_CMD_PREAMBLE_SIZE           (4)

/*  HCI Command Opcode group Field (OGF) */
#define HCI_GRP_HOST_CONT_BASEBAND_CMDS    (0x03 << 10)            /* 0x0C00 */
#define HCI_GRP_BLE_CMDS                   (0x08 << 10)

/*  HCI Command Opcode Command Field (OCF) */
#define HCI_RESET                       (0x0003 | HCI_GRP_HOST_CONT_BASEBAND_CMDS)
#define HCI_SET_EVT_MASK                (0x0001 | HCI_GRP_HOST_CONT_BASEBAND_CMDS)
/* Advertising Commands. */
#define HCI_BLE_WRITE_ADV_ENABLE        (0x000A | HCI_GRP_BLE_CMDS)
#define HCI_BLE_WRITE_ADV_DATA          (0x0008 | HCI_GRP_BLE_CMDS)
#define HCI_BLE_WRITE_ADV_PARAMS        (0x0006 | HCI_GRP_BLE_CMDS)
#define HCI_BLE_WRITE_EXT_ADV_ENABLE    (0x0039 | HCI_GRP_BLE_CMDS)
#define HCI_BLE_WRITE_EXT_ADV_DATA      (0x0037 | HCI_GRP_BLE_CMDS)
#define HCI_BLE_WRITE_EXT_ADV_PARAMS    (0x0036 | HCI_GRP_BLE_CMDS)
/* Scan commands */
#define HCI_BLE_WRITE_SCAN_PARAM        (0x000B | HCI_GRP_BLE_CMDS)
#define HCI_BLE_WRITE_SCAN_ENABLE       (0x000C | HCI_GRP_BLE_CMDS)

/* HCI Command length. */
#define HCIC_PARAM_SIZE_WRITE_ADV_ENABLE          1
#define HCIC_PARAM_SIZE_BLE_WRITE_ADV_PARAMS      15
#define HCIC_PARAM_SIZE_BLE_WRITE_ADV_DATA        31
#define HCIC_PARAM_SIZE_WRITE_EXT_ADV_ENABLE      6
#define HCIC_PARAM_SIZE_BLE_WRITE_EXT_ADV_PARAMS  25
#define HCIC_PARAM_SIZE_BLE_WRITE_EXT_ADV_DATA    251
#define HCIC_PARAM_SIZE_SET_EVENT_MASK            (8)
#define HCIC_PARAM_SIZE_BLE_WRITE_SCAN_PARAM      (7)
#define HCIC_PARAM_SIZE_BLE_WRITE_SCAN_ENABLE     (2)

/* LE Meta Events. */
#define LE_META_EVENTS                          (0x3E)
#define HCI_LE_ADV_REPORT                       (0x02)
#define HCI_LE_EXT_ADV_REPORT                   (0x0D)

#define BD_ADDR_LEN     (6)                     /* Device address length */
typedef uint8_t bd_addr_t[BD_ADDR_LEN];         /* Device address */

#define UINT16_TO_STREAM(p, u16) {*(p)++ = (uint8_t)(u16); *(p)++ = (uint8_t)((u16) >> 8);}
#define UINT8_TO_STREAM(p, u8)   {*(p)++ = (uint8_t)(u8);}
#define BDADDR_TO_STREAM(p, a)   {int ijk; for (ijk = 0; ijk < BD_ADDR_LEN;  ijk++) *(p)++ = (uint8_t) a[BD_ADDR_LEN - 1 - ijk];}
#define ARRAY_TO_STREAM(p, a, len) {int ijk; for (ijk = 0; ijk < len;        ijk++) *(p)++ = (uint8_t) a[ijk];}

enum {
    H4_TYPE_COMMAND = 1,
    H4_TYPE_ACL     = 2,
    H4_TYPE_SCO     = 3,
    H4_TYPE_EVENT   = 4
};

/**
 * @brief Writes reset bit in buf and returns size of input buffer after
 *        writing in it.
 *
 * @param buf Input buffer to write which will be sent to controller.
 *
 * @return  Size of buf after writing into it.
 */
uint16_t make_cmd_reset(uint8_t *buf);

/**
 * @brief   This function is used to request the Controller to start or stop advertising.
 *
 * @param buf         Input buffer to write which will be sent to controller.
 * @param adv_enable  1 to enable advertising and 0 to disable.
 *
 * @return  Size of buf after writing into it.
 */
uint16_t make_cmd_ble_set_adv_enable (uint8_t *buf, uint8_t adv_enable);
uint16_t make_cmd_ble_set_ext_adv_enable(uint8_t *buf, uint8_t enable, uint8_t num_sets,
    uint8_t adv_handle, uint16_t duration, uint8_t max_ext_adv_events);

/**
 * @brief   This function is used by the Host to set the advertising parameters.
 *
 * @param  buf               Input buffer to write which will be sent to controller.
 * @param  adv_int_min       Minimum advertising interval.
 * @param  adv_int_max       Maximum advertising interval.
 * @param  adv_type          Advertising type.
 * @param  addr_type_own     Own advertising type.
 * @param  addr_type_peer    Peer device's address type.
 * @param  peer_addr         Peer device's BD address.
 * @param  channel_map       Advertising channel map.
 * @param  adv_filter_policy Advertising Filter Policy.
 *
 * @return  Size of buf after writing into it.
 */
uint16_t make_cmd_ble_set_adv_param (uint8_t *buf, uint16_t adv_int_min, uint16_t adv_int_max,
                                     uint8_t adv_type, uint8_t addr_type_own,
                                     uint8_t addr_type_peer, bd_addr_t peer_addr,
                                     uint8_t channel_map, uint8_t adv_filter_policy);
uint16_t make_cmd_ble_set_ext_adv_param(uint8_t *buf, uint8_t adv_handle,
    uint16_t adv_event_properties, uint8_t *primary_adv_int_min, uint8_t *primary_adv_int_max,
    uint8_t primary_adv_channel_map, uint8_t own_addr_type, uint8_t peer_addr_type,
    bd_addr_t peer_addr, uint8_t adv_filter_policy, uint8_t adv_tx_power, uint8_t primary_adv_phy,
    uint8_t secondary_adv_max_skip, uint8_t secondary_adv_phy, uint8_t adv_sid,
    uint8_t scan_request_notification_enable);

/**
 * @brief    This function is used to set the data used in advertising packets that have a data field.
 *
 * @param   buf       Input buffer to write which will be sent to controller.
 * @param   data_len  Length of p_data.
 * @param   p_data    Data to be set.
 *
 * @return  Size of buf after writing into it.
 */
uint16_t make_cmd_ble_set_adv_data(uint8_t *buf, uint8_t data_len, uint8_t *p_data);
uint16_t make_cmd_ble_set_ext_adv_data(uint8_t *buf, uint8_t adv_handle, uint8_t operation,
    uint8_t fragment_preference, uint8_t adv_data_length, uint8_t *adv_data);

/**
 * @brief  This function is used to control which LE events are generated by the HCI for the Host.
 *         The event mask allows the Host to control which events will interrupt it.
 *
 * @param  buf          Input buffer to write which will be sent to controller.
 * @param  evt_mask     8 byte data as per spec.
 *
 * @return  Size of buf after writing into it.
 */
uint16_t make_cmd_set_evt_mask (uint8_t *buf, uint8_t *evt_mask);

/**
 * @brief   This function is used to set the scan parameters.
 *
 * @param   buf              Input buffer to write which will be sent to controller.
 * @param   scan_type        Active or Passive scanning.
 * @param   scan_interval    Set scan_interval.
 * @param   scan_window      Set scan_window.
 * @param   own_addr_type    Set own address type.
 * @param   filter_policy    Scanning filter policy.
 *
 * @return  Size of buf after writing into it.
 */
uint16_t make_cmd_ble_set_scan_params (uint8_t *buf, uint8_t scan_type,
                                       uint16_t scan_interval, uint16_t scan_window, uint8_t own_addr_type,
                                       uint8_t filter_policy);

/**
 * @brief   This function is used to set the data used in advertising packets that have a data field.
 *
 * @param   buf                 Input buffer to write which will be sent to controller.
 * @param   scan_enable         Enable or disable scanning.
 * @param   filter_duplicates   Filter duplicates enable or disable.
 *
 * @return  Size of buf after writing into it.
 */
uint16_t make_cmd_ble_set_scan_enable (uint8_t *buf, uint8_t scan_enable,
                                       uint8_t filter_duplicates);

#ifdef __cplusplus
}
#endif
