#include <stdio.h>

#include "driver/uart.h"
#include "esp_event.h"
#include "esp_netif.h"
#include "esp_openthread.h"
#include "esp_openthread_netif_glue.h"
#include "esp_vfs_eventfd.h"
#include "nvs_flash.h"
#include "openthread/ip6.h"
#include "openthread/thread.h"
#include "openthread/udp.h"

void HandleUdpReceive(void *aContext, otMessage *aMessage, const otMessageInfo *aMessageInfo) {
    printf("received %d bytes\n", otMessageGetLength(aMessage) - otMessageGetOffset(aMessage));
}

void app_main() {
    esp_vfs_eventfd_config_t eventfd_config = { 0 };
    esp_openthread_platform_config_t config = { 0 };
    esp_netif_config_t cfg = ESP_NETIF_DEFAULT_OPENTHREAD();
    esp_netif_t *openthread_netif = esp_netif_new(&cfg);
    otInstance *instance;
    otLinkModeConfig linkModeConfig;
    const otNetifAddress *unicastAddrs;
    int i = -1;
    otUdpSocket mSocket;
    otSockAddr sockaddr;

    ESP_ERROR_CHECK(nvs_flash_init());
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
    ESP_ERROR_CHECK(otIp6SetEnabled(instance, true));
    ESP_ERROR_CHECK(otThreadSetEnabled(instance, true));
    linkModeConfig = otThreadGetLinkMode(instance);
    printf("rowi dt nd=%d %d %d\n", linkModeConfig.mRxOnWhenIdle, linkModeConfig.mDeviceType,
            linkModeConfig.mNetworkData);
    printf("dr=%d\n", otThreadGetDeviceRole(instance));
    unicastAddrs = otIp6GetUnicastAddresses(instance);
    printf("address ");
    while (++i < 16) {
        printf(" %d", unicastAddrs->mAddress.mFields.m8[i]);
    }
    printf("\n");
    memcpy(&sockaddr.mAddress, &unicastAddrs->mAddress, 16);
    sockaddr.mPort = 1234;
    ESP_ERROR_CHECK(otUdpOpen(instance, &mSocket, HandleUdpReceive, NULL));
    ESP_ERROR_CHECK(otUdpBind(instance, &mSocket, &sockaddr, OT_NETIF_THREAD));
    ESP_ERROR_CHECK(esp_openthread_launch_mainloop());
}
