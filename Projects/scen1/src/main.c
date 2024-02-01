#define BLE 0
#define MQTT 1
#define COAP 2
#define ZIGBEE 3
#define THREAD 4
#define EDDYSTONE 5
#define IBEACON 6
#define PROTOCOL IBEACON

#if PROTOCOL == BLE
#include "bt_hci_common.h"
#elif PROTOCOL == MQTT
#elif PROTOCOL == COAP
#elif PROTOCOL == ZIGBEE
#elif PROTOCOL == THREAD
#elif PROTOCOL == EDDYSTONE
#elif PROTOCOL == IBEACON
#endif

#include <stdio.h>

#if PROTOCOL == BLE
#elif PROTOCOL == MQTT
#elif PROTOCOL == COAP
#include <netdb.h>
#elif PROTOCOL == ZIGBEE
#include <string.h>
#elif PROTOCOL == THREAD
#elif PROTOCOL == EDDYSTONE
#elif PROTOCOL == IBEACON
#endif

#include "driver/gpio.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "nvs_flash.h"

#if PROTOCOL == BLE
#include "esp_bt.h"
#include "esp_bt_main.h"
#elif PROTOCOL == MQTT
#include "esp_event.h"
#include "esp_netif.h"
#include "esp_timer.h"
#include "esp_wifi.h"
#include "mqtt_client.h"
#elif PROTOCOL == COAP
#include "coap3/coap.h"
#include "esp_event.h"
#include "esp_netif.h"
#include "esp_timer.h"
#include "esp_wifi.h"
#elif PROTOCOL == ZIGBEE
#include "esp_timer.h"
#include "esp_zigbee_core.h"
#elif PROTOCOL == THREAD
#include "driver/uart.h"
#include "esp_event.h"
#include "esp_netif.h"
#include "esp_openthread.h"
#include "esp_openthread_netif_glue.h"
#include "esp_timer.h"
#include "esp_vfs_eventfd.h"
#include "nvs_flash.h"
#include "openthread/ip6.h"
#include "openthread/thread.h"
#include "openthread/udp.h"
#elif PROTOCOL == EDDYSTONE
#include "esp_bt.h"
#include "host/ble_eddystone.h"
#include "host/ble_gap.h"
#include "host/util/util.h"
#include "nimble/nimble_port.h"
#include "nimble/nimble_port_freertos.h"
#elif PROTOCOL == IBEACON
#include "esp_bt.h"
#include "host/ble_ibeacon.h"
#include "host/ble_gap.h"
#include "host/util/util.h"
#include "nimble/nimble_port.h"
#include "nimble/nimble_port_freertos.h"
#endif

#if PROTOCOL == BLE
esp_vhci_host_callback_t vhci_host_cb;

uint8_t hci_cmd_buf[128];
#elif PROTOCOL == MQTT
int cts = 0;
#elif PROTOCOL == COAP
int cts = 0;
#elif PROTOCOL == ZIGBEE
int cts = 0;
#elif PROTOCOL == THREAD
#elif PROTOCOL == EDDYSTONE
int cts = 0;
#elif PROTOCOL == IBEACON
int cts = 0;
#endif

#if PROTOCOL == BLE
void controller_pkt_rcv_ready() { }

int host_rcv_pkt(uint8_t *data, uint16_t len) {
    return 0;
}
#elif PROTOCOL == MQTT
void handler_on_sta_start(void *arg, esp_event_base_t event_base, int32_t event_id,
        void *event_data) {
    printf("sta started\n");
    cts = 1;
}

void handler_on_sta_got_ip(void *arg, esp_event_base_t event_base, int32_t event_id,
        void *event_data) {
    printf("sta got ip\n");
    cts = 1;
}

void handler_on_sta_disconnected(void *arg, esp_event_base_t event_base, int32_t event_id,
        void *event_data) {
    printf("sta disconnected\n");
    cts = 1;
}

void handler_on_sta_stop(void *arg, esp_event_base_t event_base, int32_t event_id, void *event_data)
        {
    printf("sta stopped\n");
    cts = 1;
} 

void mqtt5_event_handler(void *handler_args, esp_event_base_t base, int32_t event_id,
        void *event_data) {
    esp_mqtt_event_handle_t event = event_data;

    switch ((esp_mqtt_event_id_t) event->event_id) {
    case MQTT_EVENT_CONNECTED:
        printf("mqtt connected\n");
        cts = 1;
        break;
    case MQTT_EVENT_DISCONNECTED:
        printf("mqtt disconnected\n");
        cts = 1;
        break;
    case MQTT_EVENT_ERROR:
        printf("mqtt error %d\n", event->error_handle->connect_return_code);
        break;
    default:
        printf("mqtt event %d\n", event->event_id);
    }
}
#elif PROTOCOL == COAP
void handler_on_sta_start(void *arg, esp_event_base_t event_base, int32_t event_id,
        void *event_data) {
    printf("sta started\n");
    cts = 1;
}

void handler_on_sta_got_ip(void *arg, esp_event_base_t event_base, int32_t event_id,
        void *event_data) {
    printf("sta got ip\n");
    cts = 1;
}

void handler_on_sta_disconnected(void *arg, esp_event_base_t event_base, int32_t event_id,
        void *event_data) {
    printf("sta disconnected\n");
    cts = 1;
}

void handler_on_sta_stop(void *arg, esp_event_base_t event_base, int32_t event_id, void *event_data)
        {
    printf("sta stopped\n");
    cts = 1;
}
#elif PROTOCOL == ZIGBEE
void esp_zb_task(void *pvParameters) {
    esp_zb_main_loop_iteration();
}

void esp_zb_app_signal_handler(esp_zb_app_signal_t *signal_struct) {
    switch (*signal_struct->p_app_signal) {
    case ESP_ZB_ZDO_SIGNAL_SKIP_STARTUP:
        printf("zigbee initializing\n");
        cts = 1;
        break;
    case ESP_ZB_BDB_SIGNAL_DEVICE_FIRST_START:
    case ESP_ZB_BDB_SIGNAL_DEVICE_REBOOT:
        ESP_ERROR_CHECK(signal_struct->esp_err_status);
        printf("zigbee initialized\n");
        cts = 1;
        break;
    case ESP_ZB_BDB_SIGNAL_STEERING:
        ESP_ERROR_CHECK(signal_struct->esp_err_status);
        printf("zigbee steered\n");
        cts = 1;
        break;
    default:
        printf("zigbee signal %ld status %d\n", *signal_struct->p_app_signal,
                signal_struct->esp_err_status);
    }
}

void user_cb(esp_zb_zdp_status_t zdo_status, void *user_ctx) {
    printf("zigbee left\n");
    cts = 1;
}
#elif PROTOCOL == THREAD
void ot_task_worker(void *aContext) {
    ESP_ERROR_CHECK(esp_openthread_launch_mainloop());
}
#elif PROTOCOL == EDDYSTONE
void ble_host_task(void *param) {
    nimble_port_run();
}

void on_sync() {
    ESP_ERROR_CHECK(ble_hs_util_ensure_addr(0));
    printf("ble synced\n");
    cts = 1;
}

int gap_event(struct ble_gap_event *event, void *arg) {
    printf("gap event %d\n", event->type);
    return 0;
}
#elif PROTOCOL == IBEACON
void ble_host_task(void *param) {
    nimble_port_run();
}

void on_sync() {
    ESP_ERROR_CHECK(ble_hs_util_ensure_addr(0));
    printf("ble synced\n");
    cts = 1;
}

int gap_event(struct ble_gap_event *event, void *arg) {
    printf("gap event %d\n", event->type);
    return 0;
}
#endif

void app_main() {
#if PROTOCOL == BLE
    esp_bt_controller_config_t btcfg = BT_CONTROLLER_INIT_CONFIG_DEFAULT();
    bd_addr_t addr;
#elif PROTOCOL == MQTT
    wifi_init_config_t cfg = WIFI_INIT_CONFIG_DEFAULT();
    esp_netif_inherent_config_t esp_netif_config = ESP_NETIF_INHERENT_DEFAULT_WIFI_STA();
    wifi_config_t wifi_config = { 0 };
    esp_mqtt_client_config_t mqtt5_cfg = { 0 };
    esp_mqtt_client_handle_t client;
#elif PROTOCOL == COAP
    wifi_init_config_t cfg = WIFI_INIT_CONFIG_DEFAULT();
    esp_netif_inherent_config_t esp_netif_config = ESP_NETIF_INHERENT_DEFAULT_WIFI_STA();
    wifi_config_t wifi_config = { 0 };
    coap_context_t *ctx;
    coap_uri_t uri;
    unsigned char _buf[40], *buf = _buf;
    size_t buflen = 40;
    int res;
    coap_optlist_t *optlist = NULL;
    struct addrinfo hints = { 0 }, *address;
    coap_address_t dst_addr;
    coap_session_t *session;
    coap_pdu_t *request;
#elif PROTOCOL == ZIGBEE
    esp_zb_platform_config_t config = { 0 };
    esp_zb_cfg_t zb_nwk_cfg = { 0 };
    esp_zb_attribute_list_t *esp_zb_custom_client_cluster = esp_zb_zcl_attr_list_create(0xFFFF);
    uint8_t data[2] = { 0 };
    esp_zb_cluster_list_t *esp_zb_cluster_list = esp_zb_zcl_cluster_list_create();
    esp_zb_ep_list_t *esp_zb_ep_list = esp_zb_ep_list_create();
    esp_zb_ieee_addr_t addr = { 220, 40, 247, 0, 0, 249, 85, 96 };
    esp_zb_zcl_write_attr_cmd_t cmd_req;
    esp_zb_zcl_attribute_t attr_field;
    esp_zb_zdo_mgmt_leave_req_param_t leave_req = { 0 };
#elif PROTOCOL == THREAD
    esp_vfs_eventfd_config_t eventfd_config = { 0 };
    esp_openthread_platform_config_t config = { 0 };
    esp_netif_config_t cfg = ESP_NETIF_DEFAULT_OPENTHREAD();
    esp_netif_t *openthread_netif = esp_netif_new(&cfg);
    otInstance *instance;
    otLinkModeConfig linkModeConfig;
    otSockAddr sockaddr;
    otUdpSocket mSocket;
    otMessageSettings messageSettings = { true, OT_MESSAGE_PRIORITY_NORMAL };
    otMessageInfo messageInfo = { 0 };
#elif PROTOCOL == EDDYSTONE
    struct ble_hs_adv_fields adv_fields = { 0 };
    ble_addr_t addr;
    struct ble_gap_adv_params params = { 0 };
#elif PROTOCOL == IBEACON
    uint8_t uuid[16] = { 0 };
    ble_addr_t addr;
    struct ble_gap_adv_params params = { 0 };
#endif
    int64_t time;

    gpio_reset_pin(GPIO_NUM_2);
    gpio_set_direction(GPIO_NUM_2, GPIO_MODE_OUTPUT);
    gpio_reset_pin(GPIO_NUM_4);
    gpio_set_direction(GPIO_NUM_4, GPIO_MODE_INPUT);

    ESP_ERROR_CHECK(nvs_flash_init());
#if PROTOCOL == BLE
    ESP_ERROR_CHECK(esp_bt_mem_release(ESP_BT_MODE_CLASSIC_BT));
    ESP_ERROR_CHECK(esp_bt_controller_init(&btcfg));
    ESP_ERROR_CHECK(esp_bt_controller_enable(ESP_BT_MODE_BLE));
    ESP_ERROR_CHECK(esp_bluedroid_init());
    ESP_ERROR_CHECK(esp_bluedroid_enable());
    vhci_host_cb.notify_host_send_available = controller_pkt_rcv_ready;
    vhci_host_cb.notify_host_recv = host_rcv_pkt;
    ESP_ERROR_CHECK(esp_vhci_host_register_callback(&vhci_host_cb));
    while (!esp_vhci_host_check_send_available())
        ;
    esp_vhci_host_send_packet(hci_cmd_buf,
            make_cmd_ble_set_adv_param(hci_cmd_buf, 32, 32, 3, 0, 0, addr, 1, 3));
    while (!esp_vhci_host_check_send_available())
        ;
    esp_vhci_host_send_packet(hci_cmd_buf, make_cmd_ble_set_adv_data(hci_cmd_buf, 0, hci_cmd_buf));
#elif PROTOCOL == MQTT
    ESP_ERROR_CHECK(esp_netif_init());
    ESP_ERROR_CHECK(esp_event_loop_create_default());
    ESP_ERROR_CHECK(esp_wifi_init(&cfg));
    esp_netif_config.if_desc = "netif_sta";
    esp_netif_config.route_prio = 128;
    esp_netif_create_wifi(WIFI_IF_STA, &esp_netif_config);
    ESP_ERROR_CHECK(esp_wifi_set_default_wifi_sta_handlers());
    ESP_ERROR_CHECK(esp_wifi_set_storage(WIFI_STORAGE_RAM));
    ESP_ERROR_CHECK(esp_wifi_set_mode(WIFI_MODE_STA));
    ESP_ERROR_CHECK(esp_event_handler_register(WIFI_EVENT, WIFI_EVENT_STA_START,
            handler_on_sta_start, NULL));
    ESP_ERROR_CHECK(esp_event_handler_register(IP_EVENT, IP_EVENT_STA_GOT_IP,
            handler_on_sta_got_ip, NULL));
    ESP_ERROR_CHECK(esp_event_handler_register(WIFI_EVENT, WIFI_EVENT_STA_DISCONNECTED,
            handler_on_sta_disconnected, NULL));
    ESP_ERROR_CHECK(esp_event_handler_register(WIFI_EVENT, WIFI_EVENT_STA_STOP,
            handler_on_sta_stop, NULL));
    strcpy((char *) wifi_config.sta.ssid, "dP7Ap3");
    strcpy((char *) wifi_config.sta.password, "BC6Xoq1J");
    wifi_config.sta.scan_method = WIFI_FAST_SCAN;
    wifi_config.sta.sort_method = WIFI_CONNECT_AP_BY_SIGNAL;
    wifi_config.sta.threshold.rssi = -127;
    wifi_config.sta.threshold.authmode = WIFI_AUTH_WPA2_PSK;
    ESP_ERROR_CHECK(esp_wifi_set_config(WIFI_IF_STA, &wifi_config));
    mqtt5_cfg.broker.address.uri = "mqtt://mqtt.eclipseprojects.io";
    mqtt5_cfg.session.protocol_ver = MQTT_PROTOCOL_V_5;
    mqtt5_cfg.network.disable_auto_reconnect = true;
    mqtt5_cfg.credentials.username = "123";
    mqtt5_cfg.credentials.authentication.password = "456";
    client = esp_mqtt_client_init(&mqtt5_cfg);
    esp_mqtt_client_register_event(client, ESP_EVENT_ANY_ID, mqtt5_event_handler, NULL);
#elif PROTOCOL == COAP
    ESP_ERROR_CHECK(esp_netif_init());
    ESP_ERROR_CHECK(esp_event_loop_create_default());
    ESP_ERROR_CHECK(esp_wifi_init(&cfg));
    esp_netif_config.if_desc = "netif_sta";
    esp_netif_config.route_prio = 128;
    esp_netif_create_wifi(WIFI_IF_STA, &esp_netif_config);
    ESP_ERROR_CHECK(esp_wifi_set_default_wifi_sta_handlers());
    ESP_ERROR_CHECK(esp_wifi_set_storage(WIFI_STORAGE_RAM));
    ESP_ERROR_CHECK(esp_wifi_set_mode(WIFI_MODE_STA));
    ESP_ERROR_CHECK(esp_event_handler_register(WIFI_EVENT, WIFI_EVENT_STA_START,
            handler_on_sta_start, NULL));
    ESP_ERROR_CHECK(esp_event_handler_register(IP_EVENT, IP_EVENT_STA_GOT_IP,
            handler_on_sta_got_ip, NULL));
    ESP_ERROR_CHECK(esp_event_handler_register(WIFI_EVENT, WIFI_EVENT_STA_DISCONNECTED,
            handler_on_sta_disconnected, NULL));
    ESP_ERROR_CHECK(esp_event_handler_register(WIFI_EVENT, WIFI_EVENT_STA_STOP,
            handler_on_sta_stop, NULL));
    strcpy((char *) wifi_config.sta.ssid, "dP7Ap3");
    strcpy((char *) wifi_config.sta.password, "BC6Xoq1J");
    wifi_config.sta.scan_method = WIFI_FAST_SCAN;
    wifi_config.sta.sort_method = WIFI_CONNECT_AP_BY_SIGNAL;
    wifi_config.sta.threshold.rssi = -127;
    wifi_config.sta.threshold.authmode = WIFI_AUTH_WPA2_PSK;
    ESP_ERROR_CHECK(esp_wifi_set_config(WIFI_IF_STA, &wifi_config));
    ctx = coap_new_context(NULL);
    coap_context_set_block_mode(ctx, COAP_BLOCK_USE_LIBCOAP | COAP_BLOCK_SINGLE_BODY);
    ESP_ERROR_CHECK(coap_split_uri((const uint8_t *)
            "coap://californium.eclipseprojects.io/abababababababab.d", 56, &uri));
    res = coap_split_path(uri.path.s, uri.path.length, buf, &buflen);
    while (res-- != 0) {
        coap_insert_optlist(&optlist, coap_new_optlist(COAP_OPTION_URI_PATH, coap_opt_length(buf),
                coap_opt_value(buf)));
        buf += coap_opt_size(buf);
    }
    hints.ai_socktype = SOCK_DGRAM;
    hints.ai_family = AF_INET;
    coap_address_init(&dst_addr);
#elif PROTOCOL == ZIGBEE
    config.radio_config.radio_mode = RADIO_MODE_NATIVE;
    config.host_config.host_connection_mode = HOST_CONNECTION_MODE_NONE;
    ESP_ERROR_CHECK(esp_zb_platform_config(&config));
    zb_nwk_cfg.esp_zb_role = ESP_ZB_DEVICE_TYPE_ED;
    zb_nwk_cfg.nwk_cfg.zed_cfg.ed_timeout = ESP_ZB_ED_AGING_TIMEOUT_64MIN;
    zb_nwk_cfg.nwk_cfg.zed_cfg.keep_alive = 3000;
    esp_zb_init(&zb_nwk_cfg);
    ESP_ERROR_CHECK(esp_zb_custom_cluster_add_custom_attr(esp_zb_custom_client_cluster, 0xBFFF,
            ESP_ZB_ZCL_ATTR_TYPE_LONG_OCTET_STRING, ESP_ZB_ZCL_ATTR_ACCESS_WRITE_ONLY, data));
    ESP_ERROR_CHECK(esp_zb_cluster_list_add_custom_cluster(esp_zb_cluster_list,
            esp_zb_custom_client_cluster, ESP_ZB_ZCL_CLUSTER_CLIENT_ROLE));
    ESP_ERROR_CHECK(esp_zb_ep_list_add_ep(esp_zb_ep_list, esp_zb_cluster_list, 1, 0xFFFF, 0x4FFF));
    ESP_ERROR_CHECK(esp_zb_device_register(esp_zb_ep_list));
    ESP_ERROR_CHECK(esp_zb_set_primary_network_channel_set(1 << 11));
    ESP_ERROR_CHECK(esp_zb_start(false));
    xTaskCreate(esp_zb_task, "zigbee", 4096, NULL, 5, NULL);
    memcpy(cmd_req.zcl_basic_cmd.dst_addr_u.addr_long, addr, 8);
    cmd_req.zcl_basic_cmd.dst_endpoint = 1;
    cmd_req.zcl_basic_cmd.src_endpoint = 1;
    cmd_req.address_mode = ESP_ZB_APS_ADDR_MODE_64_ENDP_PRESENT;
    cmd_req.clusterID = 0xFFFF;
    cmd_req.attr_number = 1;
    attr_field.id = 0xBFFF;
    attr_field.data.type = ESP_ZB_ZCL_ATTR_TYPE_LONG_OCTET_STRING;
    attr_field.data.size = 2;
    attr_field.data.value = data;
    cmd_req.attr_field = &attr_field;
    esp_zb_get_long_address(leave_req.device_address);
    while (cts == 0)
        ;
    cts = 0;
    ESP_ERROR_CHECK(esp_zb_bdb_start_top_level_commissioning(ESP_ZB_BDB_MODE_INITIALIZATION));
    while (cts == 0)
        ;
    cts = 0;
#elif PROTOCOL == THREAD
    ESP_ERROR_CHECK(esp_event_loop_create_default());
    ESP_ERROR_CHECK(esp_netif_init());
    eventfd_config.max_fds = 3;
    ESP_ERROR_CHECK(esp_vfs_eventfd_register(&eventfd_config));
    config.radio_config.radio_mode = RADIO_MODE_UART_RCP;
    config.radio_config.radio_uart_config.port = 1;
    config.radio_config.radio_uart_config.uart_config.baud_rate = 115200;
    config.radio_config.radio_uart_config.uart_config.data_bits = UART_DATA_8_BITS;
    config.radio_config.radio_uart_config.uart_config.parity = UART_PARITY_DISABLE;
    config.radio_config.radio_uart_config.uart_config.stop_bits = UART_STOP_BITS_1;
    config.radio_config.radio_uart_config.uart_config.flow_ctrl = UART_HW_FLOWCTRL_DISABLE;
    config.radio_config.radio_uart_config.uart_config.source_clk = UART_SCLK_DEFAULT;
    config.radio_config.radio_uart_config.rx_pin = 4;
    config.radio_config.radio_uart_config.tx_pin = 5;
    config.host_config.host_connection_mode = HOST_CONNECTION_MODE_CLI_UART;
    config.host_config.host_uart_config.port = 0;
    config.host_config.host_uart_config.uart_config.baud_rate = 115200;
    config.host_config.host_uart_config.uart_config.data_bits = UART_DATA_8_BITS;
    config.host_config.host_uart_config.uart_config.parity = UART_PARITY_DISABLE;
    config.host_config.host_uart_config.uart_config.stop_bits = UART_STOP_BITS_1;
    config.host_config.host_uart_config.uart_config.flow_ctrl = UART_HW_FLOWCTRL_DISABLE;
    config.host_config.host_uart_config.uart_config.source_clk = UART_SCLK_DEFAULT;
    config.host_config.host_uart_config.rx_pin = UART_PIN_NO_CHANGE;
    config.host_config.host_uart_config.tx_pin = UART_PIN_NO_CHANGE;
    config.port_config.storage_partition_name = "ot_storage";
    config.port_config.netif_queue_size = 10;
    config.port_config.task_queue_size = 10;
    ESP_ERROR_CHECK(esp_openthread_init(&config));
    ESP_ERROR_CHECK(esp_netif_attach(openthread_netif, esp_openthread_netif_glue_init(&config)));
    ESP_ERROR_CHECK(esp_netif_set_default_netif(openthread_netif));
    instance = esp_openthread_get_instance();
    linkModeConfig = otThreadGetLinkMode(instance);
    printf("rowi dt nd=%d %d %d\n", linkModeConfig.mRxOnWhenIdle, linkModeConfig.mDeviceType,
            linkModeConfig.mNetworkData);
    printf("dr=%d\n", otThreadGetDeviceRole(instance));
    xTaskCreate(ot_task_worker, "openthread", 4096, NULL, 5, NULL);
    ESP_ERROR_CHECK(otIp6AddressFromString("ff03::1", &sockaddr.mAddress));
    sockaddr.mPort = 1234;
#elif PROTOCOL == EDDYSTONE
    nimble_port_init();
    ble_hs_cfg.sync_cb = on_sync;
    nimble_port_freertos_init(ble_host_task);
    ESP_ERROR_CHECK(ble_eddystone_set_adv_data_url(&adv_fields, BLE_EDDYSTONE_URL_SCHEME_HTTP, "",
            0, BLE_EDDYSTONE_URL_SUFFIX_NONE, 0));
    params.itvl_min = 32;
    params.itvl_max = 32;
    params.channel_map = 1;
    params.filter_policy = 3;
    while (cts == 0)
        ;
#elif PROTOCOL == IBEACON
    nimble_port_init();
    ble_hs_cfg.sync_cb = on_sync;
    nimble_port_freertos_init(ble_host_task);
    ESP_ERROR_CHECK(ble_ibeacon_set_adv_data(uuid, 0, 0, 0));
    params.itvl_min = 32;
    params.itvl_max = 32;
    params.channel_map = 1;
    params.filter_policy = 3;
    while (cts == 0)
        ;
#endif

    while (1) {
        if (gpio_get_level(GPIO_NUM_4) == 0) {
            time = esp_timer_get_time();
            gpio_set_level(GPIO_NUM_2, 1);
#if PROTOCOL == BLE
            while (!esp_vhci_host_check_send_available())
                ;
            esp_vhci_host_send_packet(hci_cmd_buf, make_cmd_ble_set_adv_enable(hci_cmd_buf, 1));
            vTaskDelay(100 / portTICK_PERIOD_MS);
            while (!esp_vhci_host_check_send_available())
                ;
            esp_vhci_host_send_packet(hci_cmd_buf, make_cmd_ble_set_adv_enable(hci_cmd_buf, 0));
#elif PROTOCOL == MQTT
            ESP_ERROR_CHECK(esp_wifi_start());
            while (cts == 0)
                ;
            cts = 0;
            ESP_ERROR_CHECK(esp_wifi_connect());
            while (cts == 0)
                ;
            cts = 0;
            ESP_ERROR_CHECK(esp_mqtt_client_start(client));
            while (cts == 0)
                ;
            cts = 0;
            esp_mqtt_client_publish(client, "/abababababababab.d", NULL, 0, 0, 0);
            ESP_ERROR_CHECK(esp_mqtt_client_disconnect(client));
            while (cts == 0)
                ;
            cts = 0;
            ESP_ERROR_CHECK(esp_mqtt_client_stop(client));
            ESP_ERROR_CHECK(esp_wifi_disconnect());
            while (cts == 0)
                ;
            cts = 0;
            ESP_ERROR_CHECK(esp_wifi_stop());
            while (cts == 0)
                ;
            cts = 0;
#elif PROTOCOL == COAP
            ESP_ERROR_CHECK(esp_wifi_start());
            while (cts == 0)
                ;
            cts = 0;
            ESP_ERROR_CHECK(esp_wifi_connect());
            while (cts == 0)
                ;
            cts = 0;
            ESP_ERROR_CHECK(getaddrinfo("californium.eclipseprojects.io", NULL, &hints, &address));
            memcpy(&dst_addr.addr.sin, address->ai_addr, sizeof(dst_addr.addr.sin));
            dst_addr.addr.sin.sin_port = htons(uri.port);
            freeaddrinfo(address);
            session = coap_new_client_session(ctx, NULL, &dst_addr, COAP_PROTO_UDP);
            request = coap_new_pdu(COAP_MESSAGE_NON, COAP_REQUEST_CODE_PUT, session);
            coap_add_optlist_pdu(request, &optlist);
            coap_send(session, request);
            coap_session_release(session);
            ESP_ERROR_CHECK(esp_wifi_disconnect());
            while (cts == 0)
                ;
            cts = 0;
            ESP_ERROR_CHECK(esp_wifi_stop());
            while (cts == 0)
                ;
            cts = 0;
#elif PROTOCOL == ZIGBEE
            ESP_ERROR_CHECK(esp_zb_bdb_start_top_level_commissioning(
                    ESP_ZB_BDB_MODE_NETWORK_STEERING));
            while (cts == 0)
                ;
            cts = 0;
            esp_zb_zcl_write_attr_cmd_req(&cmd_req);
            esp_zb_zdo_device_leave_req(&leave_req, user_cb, NULL);
            while (cts == 0)
                ;
            cts = 0;
#elif PROTOCOL == THREAD
            ESP_ERROR_CHECK(otIp6SetEnabled(instance, true));
            ESP_ERROR_CHECK(otThreadSetEnabled(instance, true));
            ESP_ERROR_CHECK(otUdpOpen(instance, &mSocket, NULL, NULL));
            ESP_ERROR_CHECK(otUdpConnect(instance, &mSocket, &sockaddr));
            ESP_ERROR_CHECK(otUdpSend(instance, &mSocket,
                    otUdpNewMessage(instance, &messageSettings), &messageInfo));
            ESP_ERROR_CHECK(otUdpClose(instance, &mSocket));
            ESP_ERROR_CHECK(otThreadSetEnabled(instance, false));
            ESP_ERROR_CHECK(otIp6SetEnabled(instance, false));
#elif PROTOCOL == EDDYSTONE
            ESP_ERROR_CHECK(ble_gap_adv_start(0, &addr, BLE_HS_FOREVER, &params, gap_event, NULL));
            vTaskDelay(100 / portTICK_PERIOD_MS);
            ESP_ERROR_CHECK(ble_gap_adv_stop());
#elif PROTOCOL == IBEACON
            ESP_ERROR_CHECK(ble_gap_adv_start(0, &addr, BLE_HS_FOREVER, &params, gap_event, NULL));
            vTaskDelay(100 / portTICK_PERIOD_MS);
            ESP_ERROR_CHECK(ble_gap_adv_stop());
#endif
            gpio_set_level(GPIO_NUM_2, 0);
            printf("%lld\n", esp_timer_get_time() - time);
        }
        vTaskDelay(500 / portTICK_PERIOD_MS);
    }
}
