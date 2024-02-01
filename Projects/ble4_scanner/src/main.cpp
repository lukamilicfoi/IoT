#include <Arduino.h>

#include "esp_bt_main.h"
#include "esp_gap_ble_api.h"

uint8_t addr[6] = { 224, 226, 230, 177, 228, 234 };//modify as needed

void callback(esp_gap_ble_cb_event_t event, esp_ble_gap_cb_param_t *param) {
  if (event == ESP_GAP_BLE_SCAN_RESULT_EVT && memcmp(addr, param->scan_rst.bda, 6) == 0) {
    Serial.println(reinterpret_cast<char *>(param->scan_rst.ble_adv));
  }
}

void setup() {
  Serial.begin(115200);

  btStart();
  esp_bluedroid_init();
  esp_bluedroid_enable();

  Serial.println(esp_err_to_name(esp_ble_gap_register_callback(callback)));

  esp_ble_scan_params_t params;
  params.own_addr_type = BLE_ADDR_TYPE_PUBLIC;
  params.scan_duplicate = BLE_SCAN_DUPLICATE_DISABLE;
  params.scan_filter_policy = BLE_SCAN_FILTER_ALLOW_ALL;
  params.scan_interval = 0x4;
  params.scan_type = BLE_SCAN_TYPE_PASSIVE;
  params.scan_window = 0x4000;
  Serial.println(esp_err_to_name(esp_ble_gap_set_scan_params(&params)));

  Serial.println(esp_err_to_name(esp_ble_gap_start_scanning(1000+1000)));
}

void loop() { }