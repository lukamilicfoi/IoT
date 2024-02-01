/* Basic functionality for Bluetooth Host Controller Interface Layer.

   This example code is in the Public Domain (or CC0 licensed, at your option.)

   Unless required by applicable law or agreed to in writing, this
   software is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR
   CONDITIONS OF ANY KIND, either express or implied.
*/

#include "bt_hci_common.h"

uint16_t make_cmd_set_evt_mask (uint8_t *buf, uint8_t *evt_mask)
{
    UINT8_TO_STREAM (buf, H4_TYPE_COMMAND);
    UINT16_TO_STREAM (buf, HCI_SET_EVT_MASK);
    UINT8_TO_STREAM  (buf, HCIC_PARAM_SIZE_SET_EVENT_MASK);
    ARRAY_TO_STREAM(buf, evt_mask, HCIC_PARAM_SIZE_SET_EVENT_MASK);
    return HCI_H4_CMD_PREAMBLE_SIZE + HCIC_PARAM_SIZE_SET_EVENT_MASK;
}

uint16_t make_cmd_ble_set_scan_enable (uint8_t *buf, uint8_t scan_enable,
                                       uint8_t filter_duplicates)
{
    UINT8_TO_STREAM (buf, H4_TYPE_COMMAND);
    UINT16_TO_STREAM (buf, HCI_BLE_WRITE_SCAN_ENABLE);
    UINT8_TO_STREAM  (buf, HCIC_PARAM_SIZE_BLE_WRITE_SCAN_ENABLE);
    UINT8_TO_STREAM (buf, scan_enable);
    UINT8_TO_STREAM (buf, filter_duplicates);
    return HCI_H4_CMD_PREAMBLE_SIZE + HCIC_PARAM_SIZE_BLE_WRITE_SCAN_ENABLE;
}

uint16_t make_cmd_ble_set_scan_params (uint8_t *buf, uint8_t scan_type,
                                       uint16_t scan_interval, uint16_t scan_window,
                                       uint8_t own_addr_type, uint8_t filter_policy)
{
    UINT8_TO_STREAM (buf, H4_TYPE_COMMAND);
    UINT16_TO_STREAM (buf, HCI_BLE_WRITE_SCAN_PARAM);
    UINT8_TO_STREAM  (buf, HCIC_PARAM_SIZE_BLE_WRITE_SCAN_PARAM);
    UINT8_TO_STREAM (buf, scan_type);
    UINT16_TO_STREAM (buf, scan_interval);
    UINT16_TO_STREAM (buf, scan_window);
    UINT8_TO_STREAM (buf, own_addr_type);
    UINT8_TO_STREAM (buf, filter_policy);
    return HCI_H4_CMD_PREAMBLE_SIZE + HCIC_PARAM_SIZE_BLE_WRITE_SCAN_PARAM;
}

uint16_t make_cmd_ble_set_adv_enable (uint8_t *buf, uint8_t adv_enable)
{
    UINT8_TO_STREAM (buf, H4_TYPE_COMMAND);
    UINT16_TO_STREAM (buf, HCI_BLE_WRITE_ADV_ENABLE);
    UINT8_TO_STREAM  (buf, HCIC_PARAM_SIZE_WRITE_ADV_ENABLE);
    UINT8_TO_STREAM (buf, adv_enable);
    return HCI_H4_CMD_PREAMBLE_SIZE + HCIC_PARAM_SIZE_WRITE_ADV_ENABLE;
}
uint16_t make_cmd_ble_set_ext_adv_enable(uint8_t *buf, uint8_t enable, uint8_t num_sets,
        uint8_t adv_handle, uint16_t duration, uint8_t max_ext_adv_events) {
    UINT8_TO_STREAM(buf, H4_TYPE_COMMAND);
    UINT16_TO_STREAM(buf, HCI_BLE_WRITE_EXT_ADV_ENABLE);
    UINT8_TO_STREAM(buf, HCIC_PARAM_SIZE_WRITE_EXT_ADV_ENABLE);
    UINT8_TO_STREAM(buf, enable);
    UINT8_TO_STREAM(buf, num_sets);
    UINT8_TO_STREAM(buf, adv_handle);
    UINT16_TO_STREAM(buf, duration);
    UINT8_TO_STREAM(buf, max_ext_adv_events);
    return HCI_H4_CMD_PREAMBLE_SIZE + HCIC_PARAM_SIZE_WRITE_EXT_ADV_ENABLE;
}

uint16_t make_cmd_ble_set_adv_param (uint8_t *buf, uint16_t adv_int_min, uint16_t adv_int_max,
                                     uint8_t adv_type, uint8_t addr_type_own,
                                     uint8_t addr_type_dir, bd_addr_t direct_bda,
                                     uint8_t channel_map, uint8_t adv_filter_policy)
{
    UINT8_TO_STREAM (buf, H4_TYPE_COMMAND);
    UINT16_TO_STREAM (buf, HCI_BLE_WRITE_ADV_PARAMS);
    UINT8_TO_STREAM  (buf, HCIC_PARAM_SIZE_BLE_WRITE_ADV_PARAMS );

    UINT16_TO_STREAM (buf, adv_int_min);
    UINT16_TO_STREAM (buf, adv_int_max);
    UINT8_TO_STREAM (buf, adv_type);
    UINT8_TO_STREAM (buf, addr_type_own);
    UINT8_TO_STREAM (buf, addr_type_dir);
    BDADDR_TO_STREAM (buf, direct_bda);
    UINT8_TO_STREAM (buf, channel_map);
    UINT8_TO_STREAM (buf, adv_filter_policy);
    return HCI_H4_CMD_PREAMBLE_SIZE + HCIC_PARAM_SIZE_BLE_WRITE_ADV_PARAMS;
}
uint16_t make_cmd_ble_set_ext_adv_param(uint8_t *buf, uint8_t adv_handle,
        uint16_t adv_event_properties, uint8_t *primary_adv_int_min, uint8_t *primary_adv_int_max,
        uint8_t primary_adv_channel_map, uint8_t own_addr_type, uint8_t peer_addr_type,
        bd_addr_t peer_addr, uint8_t adv_filter_policy, uint8_t adv_tx_power,
        uint8_t primary_adv_phy, uint8_t secondary_adv_max_skip, uint8_t secondary_adv_phy,
        uint8_t adv_sid, uint8_t scan_request_notification_enable) {
    UINT8_TO_STREAM(buf, H4_TYPE_COMMAND);
    UINT16_TO_STREAM(buf, HCI_BLE_WRITE_EXT_ADV_PARAMS);
    UINT8_TO_STREAM(buf, HCIC_PARAM_SIZE_BLE_WRITE_EXT_ADV_PARAMS);
    UINT8_TO_STREAM(buf, adv_handle);
    UINT16_TO_STREAM(buf, adv_event_properties);
    ARRAY_TO_STREAM(buf, primary_adv_int_min, 3);
    ARRAY_TO_STREAM(buf, primary_adv_int_max, 3);
    UINT8_TO_STREAM(buf, primary_adv_channel_map);
    UINT8_TO_STREAM(buf, own_addr_type);
    UINT8_TO_STREAM(buf, peer_addr_type);
    BDADDR_TO_STREAM(buf, peer_addr);
    UINT8_TO_STREAM(buf, adv_filter_policy);
    UINT8_TO_STREAM(buf, adv_tx_power);
    UINT8_TO_STREAM(buf, primary_adv_phy);
    UINT8_TO_STREAM(buf, secondary_adv_max_skip);
    UINT8_TO_STREAM(buf, secondary_adv_phy);
    UINT8_TO_STREAM(buf, adv_sid);
    UINT8_TO_STREAM(buf, scan_request_notification_enable);
    return HCI_H4_CMD_PREAMBLE_SIZE + HCIC_PARAM_SIZE_BLE_WRITE_EXT_ADV_PARAMS;
}

uint16_t make_cmd_reset(uint8_t *buf)
{
    UINT8_TO_STREAM (buf, H4_TYPE_COMMAND);
    UINT16_TO_STREAM (buf, HCI_RESET);
    UINT8_TO_STREAM (buf, 0);
    return HCI_H4_CMD_PREAMBLE_SIZE;
}



uint16_t make_cmd_ble_set_adv_data(uint8_t *buf, uint8_t data_len, uint8_t *p_data)
{
    UINT8_TO_STREAM (buf, H4_TYPE_COMMAND);
    UINT16_TO_STREAM (buf, HCI_BLE_WRITE_ADV_DATA);
    UINT8_TO_STREAM  (buf, HCIC_PARAM_SIZE_BLE_WRITE_ADV_DATA + 1);

    memset(buf, 0, HCIC_PARAM_SIZE_BLE_WRITE_ADV_DATA);

    if (p_data != NULL && data_len > 0) {
        if (data_len > HCIC_PARAM_SIZE_BLE_WRITE_ADV_DATA) {
            data_len = HCIC_PARAM_SIZE_BLE_WRITE_ADV_DATA;
        }

        UINT8_TO_STREAM (buf, data_len);

        ARRAY_TO_STREAM (buf, p_data, data_len);
    }
    return HCI_H4_CMD_PREAMBLE_SIZE + HCIC_PARAM_SIZE_BLE_WRITE_ADV_DATA + 1;
}
uint16_t make_cmd_ble_set_ext_adv_data(uint8_t *buf, uint8_t adv_handle, uint8_t operation,
        uint8_t fragment_preference, uint8_t adv_data_length, uint8_t *adv_data) {
    UINT8_TO_STREAM(buf, H4_TYPE_COMMAND);
    UINT16_TO_STREAM(buf, HCI_BLE_WRITE_EXT_ADV_DATA);
    UINT8_TO_STREAM(buf, HCIC_PARAM_SIZE_BLE_WRITE_EXT_ADV_DATA + 4);
    UINT8_TO_STREAM(buf, adv_handle);
    UINT8_TO_STREAM(buf, operation);
    UINT8_TO_STREAM(buf, fragment_preference);
    memset(buf, 0, HCIC_PARAM_SIZE_BLE_WRITE_EXT_ADV_DATA);
    if (adv_data != NULL && adv_data_length > 0) {
        if (adv_data_length > HCIC_PARAM_SIZE_BLE_WRITE_EXT_ADV_DATA) {
            adv_data_length = HCIC_PARAM_SIZE_BLE_WRITE_EXT_ADV_DATA;
        }
        UINT8_TO_STREAM(buf, adv_data_length);
        ARRAY_TO_STREAM(buf, adv_data, adv_data_length);
    }
    return HCI_H4_CMD_PREAMBLE_SIZE + HCIC_PARAM_SIZE_BLE_WRITE_EXT_ADV_DATA + 4;
}
