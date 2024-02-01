#include <stdio.h>

#include "esp_bt.h"
#include "esp_bt_main.h"
#include "esp_gap_ble_api.h"
#include "nvs_flash.h"

uint8_t addr[6] = { 224, 226, 230, 177, 228, 234 };

void callback(esp_gap_ble_cb_event_t event, esp_ble_gap_cb_param_t *param) {
    if (event == ESP_GAP_BLE_SCAN_RESULT_EVT && memcmp(addr, param->scan_rst.bda, 6) == 0) {
        printf("%s\n", (char *) param->scan_rst.ble_adv);
    }
}

void app_main() {
    esp_bt_controller_config_t btcfg = BT_CONTROLLER_INIT_CONFIG_DEFAULT();
    esp_ble_ext_scan_params_t params = { 0 };

    ESP_ERROR_CHECK(nvs_flash_init());
    ESP_ERROR_CHECK(esp_bt_mem_release(ESP_BT_MODE_CLASSIC_BT));
    ESP_ERROR_CHECK(esp_bt_controller_init(&btcfg));
    ESP_ERROR_CHECK(esp_bt_controller_enable(ESP_BT_MODE_BLE));
    ESP_ERROR_CHECK(esp_bluedroid_init());
    ESP_ERROR_CHECK(esp_bluedroid_enable());

    ESP_ERROR_CHECK(esp_ble_gap_register_callback(callback));

    params.own_addr_type = BLE_ADDR_TYPE_PUBLIC;
    params.filter_policy = BLE_SCAN_FILTER_ALLOW_ALL;
    params.scan_duplicate = BLE_SCAN_DUPLICATE_DISABLE;
    params.cfg_mask = ESP_BLE_GAP_EXT_SCAN_CFG_UNCODE_MASK;
    params.uncoded_cfg.scan_type = BLE_SCAN_TYPE_PASSIVE;
    params.uncoded_cfg.scan_interval = 0x4;
    params.uncoded_cfg.scan_window = 0x4000;
    ESP_ERROR_CHECK(esp_ble_gap_set_ext_scan_params(&params));

    ESP_ERROR_CHECK(esp_ble_gap_start_ext_scan(1000, 10));
}
