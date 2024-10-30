# BrengerClient\DefaultApi

All URIs are relative to https://external-api.brenger.nl/v1.

Method | HTTP request | Description
------------- | ------------- | -------------
[**shipmentsPost()**](DefaultApi.md#shipmentsPost) | **POST** /shipments | Creating a shipment
[**shipmentsUuidGet()**](DefaultApi.md#shipmentsUuidGet) | **GET** /shipments/{uuid} | Get a shipment


## `shipmentsPost()`

```php
shipmentsPost($shipment): \BrengerClient\Model\CreatedShipment
```

Creating a shipment

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure API key authorization: ApiKeyAuth
$config = BrengerClient\Configuration::getDefaultConfiguration()->setApiKey('X-AUTH-TOKEN', 'YOUR_API_KEY');
// Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
// $config = BrengerClient\Configuration::getDefaultConfiguration()->setApiKeyPrefix('X-AUTH-TOKEN', 'Bearer');


$apiInstance = new BrengerClient\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$shipment = new \BrengerClient\Model\Shipment(); // \BrengerClient\Model\Shipment

try {
    $result = $apiInstance->shipmentsPost($shipment);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->shipmentsPost: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **shipment** | [**\BrengerClient\Model\Shipment**](../Model/Shipment.md)|  | [optional]

### Return type

[**\BrengerClient\Model\CreatedShipment**](../Model/CreatedShipment.md)

### Authorization

[ApiKeyAuth](../../README.md#ApiKeyAuth)

### HTTP request headers

- **Content-Type**: `application/json`
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)

## `shipmentsUuidGet()`

```php
shipmentsUuidGet($uuid): \BrengerClient\Model\CreatedShipment
```

Get a shipment

### Example

```php
<?php
require_once(__DIR__ . '/vendor/autoload.php');


// Configure API key authorization: ApiKeyAuth
$config = BrengerClient\Configuration::getDefaultConfiguration()->setApiKey('X-AUTH-TOKEN', 'YOUR_API_KEY');
// Uncomment below to setup prefix (e.g. Bearer) for API key, if needed
// $config = BrengerClient\Configuration::getDefaultConfiguration()->setApiKeyPrefix('X-AUTH-TOKEN', 'Bearer');


$apiInstance = new BrengerClient\Api\DefaultApi(
    // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
    // This is optional, `GuzzleHttp\Client` will be used as default.
    new GuzzleHttp\Client(),
    $config
);
$uuid = 40a57576-fa52-4cf2-857e-8e5b1fbe93fc; // string | The UUID of the specific shipment you want to query

try {
    $result = $apiInstance->shipmentsUuidGet($uuid);
    print_r($result);
} catch (Exception $e) {
    echo 'Exception when calling DefaultApi->shipmentsUuidGet: ', $e->getMessage(), PHP_EOL;
}
```

### Parameters

Name | Type | Description  | Notes
------------- | ------------- | ------------- | -------------
 **uuid** | [**string**](../Model/.md)| The UUID of the specific shipment you want to query |

### Return type

[**\BrengerClient\Model\CreatedShipment**](../Model/CreatedShipment.md)

### Authorization

[ApiKeyAuth](../../README.md#ApiKeyAuth)

### HTTP request headers

- **Content-Type**: Not defined
- **Accept**: `application/json`

[[Back to top]](#) [[Back to API list]](../../README.md#endpoints)
[[Back to Model list]](../../README.md#models)
[[Back to README]](../../README.md)
