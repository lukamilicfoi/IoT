#include <stdio.h>

#include "esp_zigbee_core.h"
#include "nvs_flash.h"

void esp_zb_app_signal_handler(esp_zb_app_signal_t *signal_struct) {
    esp_zb_ieee_addr_t addr;
    int i = -1;

    switch (*signal_struct->p_app_signal) {
    case ESP_ZB_ZDO_SIGNAL_SKIP_STARTUP:
        printf("zigbee initializing\n");
        ESP_ERROR_CHECK(esp_zb_bdb_start_top_level_commissioning(ESP_ZB_BDB_MODE_INITIALIZATION));
        break;
    case ESP_ZB_BDB_SIGNAL_DEVICE_FIRST_START:
    case ESP_ZB_BDB_SIGNAL_DEVICE_REBOOT:
        ESP_ERROR_CHECK(signal_struct->esp_err_status);
        printf("zigbee initialized\n");
        esp_zb_get_long_address(addr);
        printf("address ");
        while (++i < 8) {
            printf(" %d", addr[i]);
        }
        printf("\n");
        ESP_ERROR_CHECK(esp_zb_bdb_start_top_level_commissioning(
                ESP_ZB_BDB_MODE_NETWORK_FORMATION));
        break;
    case ESP_ZB_BDB_SIGNAL_FORMATION:
        ESP_ERROR_CHECK(signal_struct->esp_err_status);
        printf("zigbee formed\n");
        ESP_ERROR_CHECK(esp_zb_bdb_start_top_level_commissioning(ESP_ZB_BDB_MODE_NETWORK_STEERING));
        break;
    case ESP_ZB_BDB_SIGNAL_STEERING:
        ESP_ERROR_CHECK(signal_struct->esp_err_status);
        printf("zigbee steered\n");
        break;
    case ESP_ZB_ZDO_SIGNAL_DEVICE_ANNCE:
        printf("zigbee announced\n");
        break;
    case ESP_ZB_ZDO_SIGNAL_LEAVE_INDICATION:
        printf("zigbee left\n");
        break;
    default:
        printf("zigbee signal p_app_signal esp_err_status=%ld %d\n",
                *signal_struct->p_app_signal, signal_struct->esp_err_status);
    }
}

esp_err_t zb_attribute_handler(const esp_zb_zcl_set_attr_value_message_t *message) {
    printf("zigbee written info.cluster attribute.id=%d %d\n", message->info.cluster,
            message->attribute.id);
    return ESP_OK;
}

esp_err_t zb_action_handler(esp_zb_core_action_callback_id_t callback_id, const void *message) {
    if (callback_id == ESP_ZB_CORE_SET_ATTR_VALUE_CB_ID) {
        return zb_attribute_handler((esp_zb_zcl_set_attr_value_message_t *) message);
    }
    printf("Received zigbee action (0x%x) callback\n", callback_id);
    return ESP_OK;
}

void app_main() {
    esp_zb_platform_config_t config = { 0 };
    esp_zb_cfg_t zb_nwk_cfg = { 0 };
    esp_zb_attribute_list_t *esp_zb_custom_server_cluster = esp_zb_zcl_attr_list_create(0xFFFF);
    uint8_t data[2] = { 0 };
    esp_zb_cluster_list_t *esp_zb_cluster_list = esp_zb_zcl_cluster_list_create();
    esp_zb_ep_list_t *esp_zb_ep_list = esp_zb_ep_list_create();

    ESP_ERROR_CHECK(nvs_flash_init());
    config.radio_config.radio_mode = RADIO_MODE_NATIVE;
    config.host_config.host_connection_mode = HOST_CONNECTION_MODE_NONE;
    ESP_ERROR_CHECK(esp_zb_platform_config(&config));
    zb_nwk_cfg.esp_zb_role = ESP_ZB_DEVICE_TYPE_COORDINATOR;
    zb_nwk_cfg.install_code_policy = false;
    zb_nwk_cfg.nwk_cfg.zczr_cfg.max_children = 2;
    esp_zb_init(&zb_nwk_cfg);
    ESP_ERROR_CHECK(esp_zb_custom_cluster_add_custom_attr(esp_zb_custom_server_cluster, 0xBFFF,
            ESP_ZB_ZCL_ATTR_TYPE_LONG_OCTET_STRING, ESP_ZB_ZCL_ATTR_ACCESS_WRITE_ONLY, data));
    ESP_ERROR_CHECK(esp_zb_cluster_list_add_custom_cluster(esp_zb_cluster_list,
            esp_zb_custom_server_cluster, ESP_ZB_ZCL_CLUSTER_SERVER_ROLE));
    ESP_ERROR_CHECK(esp_zb_ep_list_add_ep(esp_zb_ep_list, esp_zb_cluster_list, 1, 0xFFFF, 0x4FFF));
    ESP_ERROR_CHECK(esp_zb_device_register(esp_zb_ep_list));
    esp_zb_core_action_handler_register(zb_action_handler);
    ESP_ERROR_CHECK(esp_zb_set_primary_network_channel_set(1 << 11));
    ESP_ERROR_CHECK(esp_zb_start(false));
    esp_zb_main_loop_iteration();
}
