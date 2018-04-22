<?php
/**
 * @author CloudRail (licobo GmbH) <dev@cloudrail.com>
 * @copyright 2016 licobo GmbH
 * @license http://cloudrail.com/licenses/LICENSE.txt
 * @link https://docs.cloudrail.com Documentation
 * @link http://cloudrail.com
 */

namespace CloudRail\Service;

use CloudRail\Error\AuthenticationError;
use CloudRail\Error\HttpError;
use CloudRail\Error\IllegalArgumentError;
use CloudRail\Error\NotFoundError;
use CloudRail\Error\ServiceUnavailableError;
use CloudRail\ServiceCode\Sandbox;
use CloudRail\Statistics;
use CloudRail\ServiceCode\InitSelfTest;
use CloudRail\ServiceCode\Interpreter;

use CloudRail\Interfaces\Payment;
use CloudRail\Type\CreditCard;
use CloudRail\Type\SubscriptionPlan;
use CloudRail\Interfaces\AdvancedRequestSupporter;
use CloudRail\Type\AdvancedRequestSpecification;
use CloudRail\Type\AdvancedRequestResponse;
use CloudRail\Type\CloudRailError;

class PayPal implements Payment, AdvancedRequestSupporter
{
	private static $SERVICE_CODE = [
		'init' => [
			["if==than", '$P0.useSandbox', 1, 2],
			["set", '$P0.baseURL', "https://api.sandbox.paypal.com/v1"],
			["jumpRel", 1],
			["set", '$P0.baseURL', "https://api.paypal.com/v1"],
			["set", '$P0.subscrCreationURL', "http://www.cloudrail.com"],
			["set", '$P0.subscrCancellationURL', "http://www.cloudrail.com"]
		],
		'createCharge' => [
			["callFunc", "checkNull", '$P0', '$P2', "Amount"],
			["callFunc", "checkLessThanZero", '$P0', '$P2', "Amount"],
			["callFunc", "checkNull", '$P0', '$P3', "Currency"],
			["callFunc", "checkEmptyString", '$P0', '$P3', "Currency"],
			["callFunc", "checkCurrency", '$P0', '$P3'],
			["callFunc", "checkNull", '$P0', '$P4', "Credit Card"],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.intent', "sale"],
			["callFunc", "createPayer", '$P0', '$L0.payer', '$P4'],
			["create", '$L1', "Array"],
			["set", '$L0.transactions', '$L1'],
			["create", '$L2', "Object"],
			["callFunc", "createTransaction", '$P0', '$L2', '$P2', '$P3'],
			["push", '$L1', '$L2'],
			["create", '$L3', "Object"],
			["set", '$L3.method', "POST"],
			["string.concat", '$L3.url', '$P0.baseURL', "/payments/payment"],
			["create", '$L4', "Object"],
			["set", '$L3.requestHeaders', '$L4'],
			["set", '$L4', "application/json", "Content-Type"],
			["string.concat", '$L5', "Bearer ", '$P0.accessToken'],
			["set", '$L4.Authorization', '$L5'],
			["json.stringify", '$L6', '$L0'],
			["stream.stringToStream", '$L3.requestBody', '$L6'],
			["http.requestCall", '$L7', '$L3'],
			["callFunc", "checkHttpErrors", '$P0', '$L7'],
			["json.parse", '$L8', '$L7.responseBody'],
			["callFunc", "extractCharge", '$P0', '$L9', '$L8'],
			["set", '$P1', '$L9']
		],
		'getCharge' => [
			["callFunc", "checkNull", '$P0', '$P2', "Charge ID"],
			["callFunc", "checkEmptyString", '$P0', '$P2', "Charge ID"],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', '$P0.baseURL', "/payments/payment/", '$P2'],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["string.concat", '$L1.Authorization', "Bearer ", '$P0.accessToken'],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "checkHttpErrors", '$P0', '$L2'],
			["json.parse", '$L4', '$L2.responseBody'],
			["callFunc", "extractCharge", '$P0', '$L3', '$L4'],
			["set", '$P1', '$L3']
		],
		'listCharges' => [
			["callFunc", "checkNull", '$P0', '$P2', "Start Time"],
			["callFunc", "checkLessThanZero", '$P0', '$P2', "Start Time"],
			["callFunc", "checkNull", '$P0', '$P3', "End Time"],
			["callFunc", "checkLessThanZero", '$P0', '$P3', "End Time"],
			["if>than", '$P2', '$P3', 2],
			["create", '$L0', "Error", "Start date can not be greater than end date.", "IllegalArgument"],
			["throwError", '$L0'],
			["create", '$L0', "Date"],
			["if>than", '$P3', '$L0.time', 2],
			["create", '$L0', "Error", "End date can not be greater than the current time.", "IllegalArgument"],
			["throwError", '$L0'],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "listPayments", '$P0', '$L0', '$P2', '$P3'],
			["if!=than", '$P4', NULL, 10],
			["create", '$L1', "Array"],
			["create", '$L2', "Number"],
			["size", '$L3', '$L0'],
			["if<than", '$L2', '$L3', 5],
			["get", '$L4', '$L0', '$L2'],
			["if==than", '$P4', '$L4.source', 1],
			["push", '$L1', '$L4'],
			["math.add", '$L2', '$L2', 1],
			["jumpRel", -6],
			["set", '$L0', '$L1'],
			["set", '$P1', '$L0']
		],
		'refundCharge' => [
			["callFunc", "checkNull", '$P0', '$P2', "Charge ID"],
			["callFunc", "checkEmptyString", '$P0', '$P2', "Charge ID"],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "executeRefund", '$P0', '$P1', '$P2']
		],
		'partiallyRefundCharge' => [
			["callFunc", "checkNull", '$P0', '$P2', "Charge ID"],
			["callFunc", "checkEmptyString", '$P0', '$P2', "Charge ID"],
			["callFunc", "checkNull", '$P0', '$P3', "Amount"],
			["callFunc", "checkLessThanZero", '$P0', '$P3', "Amount"],
			["callFunc", "checkAuthentication", '$P0'],
			["callFunc", "executeRefund", '$P0', '$P1', '$P2', '$P3']
		],
		'getRefund' => [
			["callFunc", "checkNull", '$P0', '$P2', "Refund ID"],
			["callFunc", "checkEmptyString", '$P0', '$P2', "Refund ID"],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', '$P0.baseURL', "/payments/refund/", '$P2'],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["string.concat", '$L1.Authorization', "Bearer ", '$P0.accessToken'],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "checkHttpErrors", '$P0', '$L2'],
			["json.parse", '$L3', '$L2.responseBody'],
			["callFunc", "extractRefund", '$P0', '$P1', '$L3']
		],
		'getRefundsForCharge' => [
			["callFunc", "checkNull", '$P0', '$P2', "Charge ID"],
			["callFunc", "checkEmptyString", '$P0', '$P2', "Charge ID"],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', '$P0.baseURL', "/payments/payment/", '$P2'],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["string.concat", '$L1.Authorization', "Bearer ", '$P0.accessToken'],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "checkHttpErrors", '$P0', '$L2'],
			["json.parse", '$L3', '$L2.responseBody'],
			["get", '$L4', '$L3.transactions', 0],
			["size", '$L5', '$L4.related_resources'],
			["create", '$L6', "Number", 0],
			["size", '$L7', '$L4.related_resources'],
			["create", '$P1', "Array"],
			["if<than", '$L6', '$L7', 6],
			["get", '$L8', '$L4.related_resources', '$L6'],
			["if!=than", '$L8.refund', NULL, 2],
			["callFunc", "extractRefund", '$P0', '$L9', '$L8.refund'],
			["push", '$P1', '$L9'],
			["math.add", '$L6', '$L6', 1],
			["jumpRel", -7]
		],
		'createSubscriptionPlan' => [
			["callFunc", "checkNull", '$P0', '$P2', "Name"],
			["callFunc", "checkEmptyString", '$P0', '$P2', "Name"],
			["callFunc", "checkNull", '$P0', '$P3', "Amount"],
			["callFunc", "checkNull", '$P0', '$P4', "Currency"],
			["callFunc", "checkEmptyString", '$P0', '$P4', "Currency"],
			["callFunc", "checkNull", '$P0', '$P5', "Description"],
			["callFunc", "checkEmptyString", '$P0', '$P5', "Description"],
			["callFunc", "checkNull", '$P0', '$P6', "Interval"],
			["callFunc", "checkNull", '$P0', '$P7', "Interval Count"],
			["callFunc", "checkLessThanZero", '$P0', '$P3', "Amount"],
			["callFunc", "checkLessThanZero", '$P0', '$P7', "Interval Count"],
			["callFunc", "checkCurrency", '$P0', '$P4'],
			["callFunc", "checkNull", '$P0', '$P0.subscrCreationURL', "Subscription Created URL"],
			["callFunc", "checkEmptyString", '$P0', '$P0.subscrCreationURL', "Subscription Created URL"],
			["callFunc", "checkNull", '$P0', '$P0.subscrCancellationURL', "Subscription Canceled URL"],
			["callFunc", "checkEmptyString", '$P0', '$P0.subscrCancellationURL', "Subscription Canceled URL"],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["string.concat", '$L0.url', '$P0.baseURL', "/payments/billing-plans"],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["string.concat", '$L1.Authorization', "Bearer ", '$P0.accessToken'],
			["set", '$L1.Content-Type', "application/json"],
			["create", '$L2', "Object"],
			["set", '$L2.name', '$P2'],
			["set", '$L2.description', '$P5'],
			["set", '$L2.type', "INFINITE"],
			["create", '$L3', "Array"],
			["set", '$L2.payment_definitions', '$L3'],
			["create", '$L4', "Object"],
			["push", '$L3', '$L4'],
			["set", '$L4.name', "Regular Payments"],
			["set", '$L4.type', "REGULAR"],
			["set", '$L4.frequency_interval', '$P7'],
			["string.concat", '$L4.frequency_interval', '$L4.frequency_interval'],
			["set", '$L4.cycles', "0"],
			["callFunc", "getFrequency", '$P0', '$L4.frequency', '$P6'],
			["create", '$L5', "Object"],
			["set", '$L4.amount', '$L5'],
			["set", '$L5.currency', '$P4'],
			["callFunc", "normalizeAmount", '$P0', '$L5.value', '$P3'],
			["create", '$L20', "Object"],
			["set", '$L2.merchant_preferences', '$L20'],
			["set", '$L20.cancel_url', '$P0.subscrCancellationURL'],
			["set", '$L20.return_url', '$P0.subscrCreationURL'],
			["set", '$L20.auto_bill_amount', "YES"],
			["json.stringify", '$L6', '$L2'],
			["stream.stringToStream", '$L0.requestBody', '$L6'],
			["http.requestCall", '$L7', '$L0'],
			["callFunc", "checkHttpErrors", '$P0', '$L7'],
			["json.parse", '$L8', '$L7.responseBody'],
			["callFunc", "extractSubscriptionPlan", '$P0', '$L9', '$L8'],
			["create", '$L10', "Object"],
			["set", '$L10.method', "POST"],
			["string.concat", '$L10.url', '$P0.baseURL', "/payments/billing-plans/", '$L9.id'],
			["create", '$L11', "Object"],
			["string.concat", '$L11.Authorization', "Bearer ", '$P0.accessToken'],
			["set", '$L11.Content-Type', "application/json"],
			["set", '$L11.X-HTTP-METHOD-OVERRIDE', "PATCH"],
			["set", '$L10.requestHeaders', '$L11'],
			["create", '$L12', "Array"],
			["create", '$L13', "Object"],
			["set", '$L13.path', "/"],
			["set", '$L13.op', "replace"],
			["create", '$L14', "Object"],
			["set", '$L14.state', "ACTIVE"],
			["set", '$L13.value', '$L14'],
			["push", '$L12', '$L13'],
			["json.stringify", '$L15', '$L12'],
			["stream.stringToStream", '$L10.requestBody', '$L15'],
			["http.requestCall", '$L16', '$L10'],
			["callFunc", "checkHttpErrors", '$P0', '$L16'],
			["set", '$P1', '$L9']
		],
		'listSubscriptionPlans' => [
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L20', "Array"],
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', '$P0.baseURL', "/payments/billing-plans?status=active&page_size=20"],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["string.concat", '$L1.Authorization', "Bearer ", '$P0.accessToken'],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "checkHttpErrors", '$P0', '$L2'],
			["json.parse", '$L3', '$L2.responseBody'],
			["create", '$L4', "Number"],
			["size", '$L5', '$L3.plans'],
			["if<than", '$L4', '$L5', 10],
			["get", '$L6', '$L3.plans', '$L4'],
			["get", '$L7', '$L6.links', 0],
			["set", '$L0.url', '$L7.href'],
			["http.requestCall", '$L8', '$L0'],
			["callFunc", "checkHttpErrors", '$P0', '$L8'],
			["json.parse", '$L9', '$L8.responseBody'],
			["callFunc", "extractSubscriptionPlan", '$P0', '$L10', '$L9'],
			["push", '$L20', '$L10'],
			["math.add", '$L4', '$L4', 1],
			["jumpRel", -11],
			["create", '$L4', "Number"],
			["size", '$L5', '$L3.links'],
			["if<than", '$L4', '$L5', 8],
			["get", '$L6', '$L3.links', '$L4'],
			["if==than", '$L6.rel', "next_page", 4],
			["set", '$L0.url', '$L6.href'],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "checkHttpErrors", '$P0', '$L2'],
			["jumpRel", -23],
			["math.add", '$L4', '$L4', 1],
			["jumpRel", -9],
			["set", '$P1', '$L20']
		],
		'createSubscription' => [
			["callFunc", "checkNull", '$P0', '$P2', "Subscription Plan ID"],
			["callFunc", "checkEmptyString", '$P0', '$P2', "Subscription Plan ID"],
			["callFunc", "checkNull", '$P0', '$P3', "Subscription Name"],
			["callFunc", "checkEmptyString", '$P0', '$P3', "Subscription Name"],
			["callFunc", "checkNull", '$P0', '$P4', "Subscription Description"],
			["callFunc", "checkEmptyString", '$P0', '$P4', "Subscription Description"],
			["callFunc", "checkNull", '$P0', '$P5', "Credit Card"],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["string.concat", '$L0.url', '$P0.baseURL', "/payments/billing-agreements"],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["string.concat", '$L1.Authorization', "Bearer ", '$P0.accessToken'],
			["set", '$L1.Content-Type', "application/json"],
			["create", '$L2', "Object"],
			["set", '$L2.name', '$P3'],
			["set", '$L2.description', '$P4'],
			["create", '$L3', "Date"],
			["math.add", '$L3.time', '$L3.time', 120000],
			["set", '$L2.start_date', '$L3.rfcTime'],
			["callFunc", "createPayer", '$P0', '$L2.payer', '$P5'],
			["create", '$L4', "Object"],
			["set", '$L4.id', '$P2'],
			["set", '$L2.plan', '$L4'],
			["json.stringify", '$L5', '$L2'],
			["stream.stringToStream", '$L0.requestBody', '$L5'],
			["http.requestCall", '$L6', '$L0'],
			["callFunc", "checkHttpErrors", '$P0', '$L6'],
			["json.parse", '$L7', '$L6.responseBody'],
			["set", '$L7.name', '$P3'],
			["set", '$L7.plan.id', '$P2'],
			["callFunc", "extractSubscription", '$P0', '$P1', '$L7']
		],
		'updateSubscription' => [
			["callFunc", "checkNull", '$P0', '$P2', "Subscription ID"],
			["callFunc", "checkEmptyString", '$P0', '$P2', "Subscription Plan ID"],
			["if==than", '$P3', NULL, 3],
			["if==than", '$P4', NULL, 2],
			["create", '$L0', "Error", "Provide a new credit card or a new subscription plan.", "IllegalArgument"],
			["throwError", '$L0'],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["string.concat", '$L0.url', '$P0.baseURL', "/payments/billing-agreements/", '$P2'],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["string.concat", '$L1.Authorization', "Bearer ", '$P0.accessToken'],
			["set", '$L1.X-HTTP-METHOD-OVERRIDE', "PATCH"],
			["set", '$L1.Content-Type', "application/json"],
			["create", '$L2', "Array"],
			["create", '$L3', "Object"],
			["set", '$L3.op', "replace"],
			["set", '$L3.path', "/"],
			["create", '$L4', "Object"],
			["if!=than", '$P3', NULL, 3],
			["create", '$L5', "Object"],
			["set", '$L5.id', '$P3'],
			["set", '$L4.plan', '$L5'],
			["if!=than", '$P4', NULL, 1],
			["callFunc", "createPayer", '$P0', '$L4.payer', '$P4'],
			["set", '$L3.value', '$L4'],
			["push", '$L2', '$L3'],
			["json.stringify", '$L6', '$L2'],
			["stream.stringToStream", '$L0.requestBody', '$L6'],
			["http.requestCall", '$L7', '$L0'],
			["callFunc", "checkHttpErrors", '$P0', '$L7'],
			["json.parse", '$L8', '$L7.responseBody'],
			["callFunc", "extractSubscription", '$P0', '$P1', '$L8']
		],
		'cancelSubscription' => [
			["callFunc", "checkNull", '$P0', '$P1', "Subscription ID"],
			["callFunc", "checkEmptyString", '$P0', '$P2', "Subscription Plan ID"],
			["callFunc", "checkAuthentication", '$P0'],
			["create", '$L0', "Object"],
			["set", '$L0.method', "POST"],
			["string.concat", '$L0.url', '$P0.baseURL', "/payments/billing-agreements/", '$P1', "/cancel"],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["string.concat", '$L1.Authorization', "Bearer ", '$P0.accessToken'],
			["set", '$L1.Content-Type', "application/json"],
			["create", '$L2', "Object"],
			["set", '$L2.note', "Cancelling the agreement."],
			["json.stringify", '$L3', '$L2'],
			["stream.stringToStream", '$L0.requestBody', '$L3'],
			["http.requestCall", '$L4', '$L0'],
			["callFunc", "checkHttpErrors", '$P0', '$L4']
		],
		'AdvancedRequestSupporter:advancedRequest' => [
			["create", '$L0', "Object"],
			["if!=than", '$P2.appendBaseUrl', 0, 2],
			["string.concat", '$L0.url', '$P0.baseURL', '$P2.url'],
			["jumpRel", 1],
			["set", '$L0.url', '$P2.url'],
			["set", '$L0.requestHeaders', '$P2.headers'],
			["set", '$L0.method', '$P2.method'],
			["set", '$L0.requestBody', '$P2.body'],
			["if==than", '$L0.requestHeaders', NULL, 1],
			["create", '$L0.requestHeaders', "Object"],
			["if!=than", '$P2.appendAuthorization', 0, 2],
			["callFunc", "checkAuthentication", '$P0'],
			["string.concat", '$L0.requestHeaders.Authorization', "Bearer ", '$P0.accessToken'],
			["http.requestCall", '$L1', '$L0'],
			["if!=than", '$P2.checkErrors', 0, 1],
			["callFunc", "checkHttpErrors", '$P0', '$L1'],
			["create", '$P1', "AdvancedRequestResponse"],
			["set", '$P1.status', '$L1.code'],
			["set", '$P1.headers', '$L1.responseHeaders'],
			["set", '$P1.body', '$L1.responseBody']
		],
		'checkAuthentication' => [
			["create", '$L0', "Date"],
			["if==than", '$S0.accessToken', NULL, 2],
			["callFunc", "authenticate", '$P0'],
			["return"],
			["create", '$L1', "Date"],
			["set", '$L1.time', '$S0.expiresIn'],
			["if<than", '$L1', '$L0', 1],
			["callFunc", "authenticate", '$P0']
		],
		'authenticate' => [
			["create", '$L0', "Object"],
			["string.concat", '$L0.url', '$P0.baseURL', "/oauth2/token"],
			["set", '$L0.method', "POST"],
			["stream.stringToStream", '$L0.requestBody', "grant_type=client_credentials"],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["set", '$L1.Accept', "application/json"],
			["set", '$L1', "application/x-www-form-urlencoded", "Content-Type"],
			["string.concat", '$L2', '$P0.clientId', ":", '$P0.clientSecret'],
			["string.base64encode", '$L2', '$L2'],
			["string.concat", '$L2', "Basic ", '$L2'],
			["set", '$L1.Authorization', '$L2'],
			["http.requestCall", '$L3', '$L0'],
			["callFunc", "checkHttpErrors", '$P0', '$L3'],
			["json.parse", '$L4', '$L3.responseBody'],
			["set", '$P0.accessToken', '$L4.access_token'],
			["create", '$L5', "Date"],
			["math.multiply", '$L6', '$L4.expires_in', 1000],
			["math.add", '$L6', '$L6', '$L5.time', -60000],
			["set", '$P0.expiresIn', '$L6']
		],
		'checkNull' => [
			["if==than", '$P1', NULL, 3],
			["string.concat", '$L0', '$P2', " is not allowed to be null."],
			["create", '$L1', "Error", '$L0', "IllegalArgument"],
			["throwError", '$L1']
		],
		'checkEmptyString' => [
			["if==than", '$P1', "", 3],
			["string.concat", '$L0', '$P2', " is not allowed to be empty."],
			["create", '$L1', "Error", '$L0', "IllegalArgument"],
			["throwError", '$L1']
		],
		'checkLessThanZero' => [
			["if<=than", '$P1', 0, 3],
			["string.concat", '$L0', '$P2', " is not allowed to be less than 0."],
			["create", '$L1', "Error", '$L0', "IllegalArgument"],
			["throwError", '$L1']
		],
		'checkCurrency' => [
			["size", '$L0', '$P1'],
			["if!=than", '$L0', 3, 2],
			["create", '$L1', "Error", "Currency code has to have three letters.", "IllegalArgument"],
			["throwError", '$L1']
		],
		'checkHttpErrors' => [
			["if==than", '$P1.code', 429, 2],
			["create", '$L0', "Error", "Rate limit exceeded!", "Http"],
			["throwError", '$L0'],
			["if>=than", '$P1.code', 500, 8],
			["if==than", '$P1.code', 503, 4],
			["string.concat", '$L10', "The reurned code was ", '$P1.code', " ", '$P1.message'],
			["create", '$L3', "Error", '$L10', "ServiceUnavailable"],
			["throwError", '$L3'],
			["jumpRel", 3],
			["string.concat", '$L10', "The returned code was ", '$P1.code', " ", '$P1.message'],
			["create", '$L3', "Error", '$L10', "Http"],
			["throwError", '$L3'],
			["if>=than", '$P1.code', 400, 26],
			["json.parse", '$L0', '$P1.responseBody'],
			["set", '$L2', '$L0.message'],
			["if==than", '$L2', NULL, 1],
			["set", '$L2', '$L0.error_description'],
			["if==than", '$L2', "", 1],
			["set", '$L2', '$L0.error_description'],
			["if==than", '$L2', NULL, 1],
			["set", '$L2', '$L0.error.message'],
			["if==than", '$L2', "", 1],
			["set", '$L2', '$L0.error.message'],
			["if==than", '$L2', NULL, 3],
			["if!=than", '$L0.field', NULL, 2],
			["get", '$L10', '$L0.field.details', 0],
			["set", '$L2', '$L10.issue'],
			["if==than", '$L2', "", 3],
			["if!=than", '$L0.field', NULL, 2],
			["get", '$L10', '$L0.field.details', 0],
			["set", '$L2', '$L10.issue'],
			["if==than", '$P1.code', 401, 2],
			["create", '$L3', "Error", '$L2', "Authentication"],
			["throwError", '$L3'],
			["if==than", '$P1.code', 404, 2],
			["create", '$L3', "Error", '$L2', "NotFound"],
			["throwError", '$L3'],
			["create", '$L3', "Error", '$L2', "Http"],
			["throwError", '$L3']
		],
		'createPayer' => [
			["create", '$L0', "Object"],
			["set", '$L0.payment_method', "credit_card"],
			["create", '$L1', "Array"],
			["set", '$L0.funding_instruments', '$L1'],
			["create", '$L2', "Object"],
			["push", '$L1', '$L2'],
			["create", '$L3', "Object"],
			["set", '$L2.credit_card', '$L3'],
			["set", '$L3.number', '$P2.number'],
			["set", '$L3.type', '$P2.type'],
			["string.concat", '$L4', '$P2.expire_month'],
			["set", '$L3.expire_month', '$L4'],
			["string.concat", '$L4', '$P2.expire_year'],
			["set", '$L3.expire_year', '$L4'],
			["set", '$L3.first_name', '$P2.firstName'],
			["set", '$L3.last_name', '$P2.lastName'],
			["if!=than", '$P2.cvc', NULL, 1],
			["set", '$L3.cvv2', '$P2.cvc'],
			["if!=than", '$P2.address', NULL, 8],
			["create", '$L5', "Object"],
			["set", '$L3.billing_address', '$L5'],
			["set", '$L5.line1', '$P2.address.line1'],
			["set", '$L5.line2', '$P2.address.line2'],
			["set", '$L5.city', '$P2.address.city'],
			["set", '$L5.country_code', '$P2.address.country'],
			["set", '$L5.postal_code', '$P2.address.postalCode'],
			["set", '$L5.state', '$P2.address.state'],
			["set", '$P1', '$L0']
		],
		'createTransaction' => [
			["create", '$L0', "Object"],
			["create", '$L1', "Object"],
			["set", '$L0.amount', '$L1'],
			["set", '$L1.currency', '$P3'],
			["callFunc", "normalizeAmount", '$P0', '$L1.total', '$P2'],
			["set", '$P1', '$L0']
		],
		'extractCharge' => [
			["set", '$L0', '$P2'],
			["get", '$L1', '$L0.transactions', 0],
			["math.multiply", '$L2', '$L1.amount.total', 100],
			["if<than", '$L2', 0, 1],
			["math.multiply", '$L2', '$L2', -1],
			["string.concat", '$L2', '$L2'],
			["math.add", '$L2', '$L2'],
			["create", '$L3', "Date", '$L0.create_time'],
			["get", '$L4', '$L1.amount.currency'],
			["get", '$L5', '$L0.id'],
			["get", '$L6', '$L1.related_resources', 0],
			["get", '$L7', '$L6.sale.state'],
			["if==than", '$L7', "refunded", 2],
			["set", '$L7', 1],
			["jumpRel", 1],
			["set", '$L7', 0],
			["callFunc", "extractCreditCard", '$P0', '$L13', '$L0.payer'],
			["get", '$L14', '$L0.state'],
			["if==than", '$L14', "created", 2],
			["set", '$L14', "pending"],
			["jumpRel", 8],
			["if==than", '$L14', "approved", 2],
			["set", '$L14', "succeeded"],
			["jumpRel", 5],
			["if==than", '$L14', "canceled", 2],
			["set", '$L14', "failed"],
			["jumpRel", 2],
			["if==than", '$L14', "expired", 2],
			["set", '$L14', "failed"],
			["create", '$P1', "Charge", '$L2', '$L3.time', '$L4', '$L5', '$L7', '$L13', '$L14']
		],
		'listPayments' => [
			["create", '$L0', "Array"],
			["create", '$L10', "Date"],
			["set", '$L10.time', '$P2'],
			["string.urlEncode", '$L10', '$L10.rfcTime'],
			["create", '$L11', "Date"],
			["set", '$L11.time', '$P3'],
			["string.urlEncode", '$L11', '$L11.rfcTime'],
			["create", '$L1', "Object"],
			["set", '$L1.method', "GET"],
			["string.concat", '$L1.url', '$P0.baseURL', "/payments/payment?sort_by=create_time&", "start_time=", '$L10', "&end_time=", '$L11', "&count=20"],
			["if!=than", '$L5', NULL, 1],
			["string.concat", '$L1.url', '$L1.url', "&start_id=", '$L5'],
			["create", '$L2', "Object"],
			["set", '$L1.requestHeaders', '$L2'],
			["string.concat", '$L2.Authorization', "Bearer ", '$P0.accessToken'],
			["http.requestCall", '$L3', '$L1'],
			["callFunc", "checkHttpErrors", '$P0', '$L3'],
			["json.parse", '$L4', '$L3.responseBody'],
			["set", '$L5', '$L4.next_id'],
			["create", '$L6', "Number", 0],
			["get", '$L7', '$L4.count'],
			["if<than", '$L6', '$L7', 5],
			["get", '$L8', '$L4.payments', '$L6'],
			["callFunc", "extractCharge", '$P0', '$L9', '$L8'],
			["push", '$L0', '$L9'],
			["math.add", '$L6', '$L6', 1],
			["jumpRel", -6],
			["if!=than", '$L5', NULL, 1],
			["jumpRel", -22],
			["set", '$P1', '$L0']
		],
		'executeRefund' => [
			["create", '$L0', "Object"],
			["set", '$L0.method', "GET"],
			["string.concat", '$L0.url', '$P0.baseURL', "/payments/payment/", '$P2'],
			["create", '$L1', "Object"],
			["set", '$L0.requestHeaders', '$L1'],
			["string.concat", '$L1.Authorization', "Bearer ", '$P0.accessToken'],
			["http.requestCall", '$L2', '$L0'],
			["callFunc", "checkHttpErrors", '$P0', '$L2'],
			["json.parse", '$L3', '$L2.responseBody'],
			["get", '$L4', '$L3.transactions', 0],
			["set", '$L8', '$L4.amount.currency'],
			["get", '$L4', '$L4.related_resources', 0],
			["set", '$L4', '$L4.sale.id'],
			["create", '$L5', "Object"],
			["set", '$L5.method', "POST"],
			["string.concat", '$L5.url', '$P0.baseURL', "/payments/sale/", '$L4', "/refund"],
			["create", '$L6', "Object"],
			["set", '$L5.requestHeaders', '$L6'],
			["string.concat", '$L6.Authorization', "Bearer ", '$P0.accessToken'],
			["set", '$L6', "application/json", "Content-Type"],
			["create", '$L7', "Object"],
			["if!=than", '$P3', NULL, 4],
			["create", '$L9', "Object"],
			["set", '$L7.amount', '$L9'],
			["set", '$L9.currency', '$L8'],
			["callFunc", "normalizeAmount", '$P0', '$L9.total', '$P3'],
			["json.stringify", '$L5.requestBody', '$L7'],
			["stream.stringToStream", '$L5.requestBody', '$L5.requestBody'],
			["http.requestCall", '$L9', '$L5'],
			["callFunc", "checkHttpErrors", '$P0', '$L9'],
			["json.parse", '$L10', '$L9.responseBody'],
			["callFunc", "extractRefund", '$P0', '$P1', '$L10']
		],
		'normalizeAmount' => [
			["math.multiply", '$L0', '$P2', 0.01],
			["string.concat", '$L0', '$L0'],
			["string.indexOf", '$L1', '$L0', "."],
			["size", '$L2', '$L0'],
			["math.multiply", '$L1', '$L1', -1],
			["math.add", '$L3', '$L1', '$L2'],
			["if==than", '$L3', 2, 1],
			["string.concat", '$L0', '$L0', "0"],
			["set", '$P1', '$L0']
		],
		'extractRefund' => [
			["math.multiply", '$L0', '$P2.amount.total', 100],
			["if<than", '$L0', 0, 1],
			["math.multiply", '$L0', '$L0', -1],
			["string.concat", '$L0', '$L0'],
			["math.add", '$L0', '$L0'],
			["get", '$L1', '$P2.parent_payment'],
			["create", '$L2', "Date", '$P2.create_time'],
			["set", '$L2', '$L2.time'],
			["get", '$L3', '$P2.id'],
			["get", '$L4', '$P2.state'],
			["if==than", '$L4', "completed", 1],
			["set", '$L4', "succeeded"],
			["get", '$L5', '$P2.amount.currency'],
			["create", '$P1', "Refund", '$L0', '$L1', '$L2', '$L3', '$L4', '$L5']
		],
		'getFrequency' => [
			["if==than", '$P2', "day", 2],
			["set", '$P1', "DAY"],
			["return"],
			["if==than", '$P2', "week", 2],
			["set", '$P1', "WEEK"],
			["return"],
			["if==than", '$P2', "month", 2],
			["set", '$P1', "MONTH"],
			["return"],
			["if==than", '$P2', "year", 2],
			["set", '$P1', "YEAR"],
			["return"],
			["create", '$L0', "Error", "Invalid interval. Allowed values are: 'day', 'week', 'month' or 'year'.", "IllegalArgument"],
			["throwError", '$L0']
		],
		'getInterval' => [
			["if==than", '$P2', "Day", 2],
			["set", '$P1', "day"],
			["return"],
			["if==than", '$P2', "Week", 2],
			["set", '$P1', "week"],
			["return"],
			["if==than", '$P2', "Month", 2],
			["set", '$P1', "month"],
			["return"],
			["set", '$P1', "year"]
		],
		'extractSubscriptionPlan' => [
			["get", '$L0', '$P2.payment_definitions', 0],
			["math.multiply", '$L1', '$L0.amount.value', 100],
			["string.concat", '$L1', '$L1'],
			["math.add", '$L1', '$L1'],
			["create", '$L2', "Date", '$P2.create_time'],
			["callFunc", "getInterval", '$P0', '$L3', '$L0.frequency'],
			["math.add", '$L4', '$L0.frequency_interval', 0],
			["create", '$P1', "SubscriptionPlan", '$L1', '$L2.time', '$L0.amount.currency', '$P2.description', '$P2.id', '$L3', '$L4', '$P2.name']
		],
		'extractSubscription' => [
			["create", '$L0', "Date", '$P2.start_date'],
			["if!=than", '$P2.agreement_details.last_payment_date', NULL, 2],
			["create", '$L1', "Date", '$P2.agreement_details.last_payment_date'],
			["set", '$L1', '$L1.time'],
			["create", '$L2', "Date", '$P2.agreement_details.next_billing_date'],
			["callFunc", "extractCreditCard", '$P0', '$L3', '$P2.payer'],
			["get", '$L4', '$P2.state'],
			["if==than", '$L4', "Active", 1],
			["set", '$L4', "active"],
			["if==than", '$L4', "Pending", 1],
			["set", '$L4', "active"],
			["if==than", '$L4', "Expired", 1],
			["set", '$L4', "cancelled"],
			["if==than", '$L4', "Suspend", 1],
			["set", '$L4', "cancelled"],
			["if==than", '$L4', "Reactivated", 1],
			["set", '$L4', "active"],
			["if==than", '$L4', "Cancel", 1],
			["set", '$L4', "cancelled"],
			["create", '$P1', "Subscription", '$L0.time', '$P2.description', '$P2.id', '$L1', '$P2.name', '$L2.time', '$L3', '$L4', '$P2.plan.id']
		],
		'extractCreditCard' => [
			["get", '$L8', '$P2.funding_instruments', 0],
			["get", '$L9', '$L8.credit_card.type'],
			["get", '$L10', '$L8.credit_card.number'],
			["get", '$L11', '$L8.credit_card.expire_year'],
			["math.add", '$L11', '$L11', 0],
			["get", '$L12', '$L8.credit_card.expire_month'],
			["math.add", '$L12', '$L12', 0],
			["get", '$L13', '$L8.credit_card.cvv2'],
			["get", '$L15', '$L8.credit_card.first_name'],
			["get", '$L16', '$L8.credit_card.last_name'],
			["size", '$L0', '$L10'],
			["if==than", '$L0', 4, 1],
			["string.concat", '$L10', "xxxxxxxxxxxx", '$L10'],
			["get", '$L17', '$L8.credit_card.billing_address'],
			["set", '$L18', NULL],
			["if!=than", '$L17', NULL, 13],
			["create", '$L18', "Address"],
			["if!=than", '$L17.line1', NULL, 1],
			["set", '$L18.line1', '$L17.line1'],
			["if!=than", '$L17.line2', NULL, 1],
			["set", '$L18.line2', '$L17.line2'],
			["if!=than", '$L17.country_code', NULL, 1],
			["set", '$L18.country', '$L17.country_code'],
			["if!=than", '$L17.city', NULL, 1],
			["set", '$L18.city', '$L17.city'],
			["if!=than", '$L17.postal_code', NULL, 1],
			["set", '$L18.postalCode', '$L17.postal_code'],
			["if!=than", '$L17.state', NULL, 1],
			["set", '$L18.state', '$L17.state'],
			["create", '$P1', "CreditCard", '$L13', '$L12', '$L11', '$L10', '$L9', '$L15', '$L16', '$L18']
		]
	];

	/** @var mixed[] */
	private $interpreterStorage;

	/** @var mixed[] */
	private $instanceDependencyStorage;

	/** @var mixed[] */
	private $persistentStorage;
	
	
	/**
	 * 
	 * @param bool $useSandbox
	 * @param string $clientId
	 * @param string $clientSecret
	 */
	public function __construct(callable $redirectReceiver, bool $useSandbox, string $clientId, string $clientSecret)
	{
		$this->interpreterStorage = array();
		$this->instanceDependencyStorage = ["redirectReceiver" => $redirectReceiver];
		$this->persistentStorage = array(array());
		InitSelfTest::initTest('PayPal');
		
		$this->interpreterStorage['useSandbox'] = $useSandbox;
		$this->interpreterStorage['clientId'] = $clientId;
		$this->interpreterStorage['clientSecret'] = $clientSecret;
		

		$ip = new Interpreter(new Sandbox(PayPal::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		if (array_key_exists("init",PayPal::$SERVICE_CODE)) {
			$parameters = [&$this->interpreterStorage];
		  $ip->callFunctionSync("init",$parameters );
		}
	}

	
	/**
	 * @param int $amount
	 * @param string $currency
	 * @param CreditCard $source
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return Charge
	 */
	public function createCharge(int $amount, string $currency, CreditCard $source):Charge {
		Statistics::addCall("PayPal", "createCharge");
		$ip = new Interpreter(new Sandbox(PayPal::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $amount, $currency, $source];
		$ip->callFunctionSync('createCharge', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	
	/**
	 * @param string $id
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return Charge
	 */
	public function getCharge(string $id):Charge {
		Statistics::addCall("PayPal", "getCharge");
		$ip = new Interpreter(new Sandbox(PayPal::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $id];
		$ip->callFunctionSync('getCharge', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	
	/**
	 * @param int $from
	 * @param int $to
	 * @param CreditCard $creditCard
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return array
	 */
	public function listCharges(int $from, int $to, CreditCard $creditCard):array {
		Statistics::addCall("PayPal", "listCharges");
		$ip = new Interpreter(new Sandbox(PayPal::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $from, $to, $creditCard];
		$ip->callFunctionSync('listCharges', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	
	/**
	 * @param string $id
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return Refund
	 */
	public function refundCharge(string $id):Refund {
		Statistics::addCall("PayPal", "refundCharge");
		$ip = new Interpreter(new Sandbox(PayPal::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $id];
		$ip->callFunctionSync('refundCharge', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	
	/**
	 * @param string $id
	 * @param int $amount
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return Refund
	 */
	public function partiallyRefundCharge(string $id, int $amount):Refund {
		Statistics::addCall("PayPal", "partiallyRefundCharge");
		$ip = new Interpreter(new Sandbox(PayPal::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $id, $amount];
		$ip->callFunctionSync('partiallyRefundCharge', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	
	/**
	 * @param string $id
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return Refund
	 */
	public function getRefund(string $id):Refund {
		Statistics::addCall("PayPal", "getRefund");
		$ip = new Interpreter(new Sandbox(PayPal::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $id];
		$ip->callFunctionSync('getRefund', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	
	/**
	 * @param string $id
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return array
	 */
	public function getRefundsForCharge(string $id):array {
		Statistics::addCall("PayPal", "getRefundsForCharge");
		$ip = new Interpreter(new Sandbox(PayPal::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $id];
		$ip->callFunctionSync('getRefundsForCharge', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	
	/**
	 * @param string $name
	 * @param int $amount
	 * @param string $currency
	 * @param string $description
	 * @param string $interval
	 * @param int $intervalCount
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return SubscriptionPlan
	 */
	public function createSubscriptionPlan(string $name, int $amount, string $currency, string $description, string $interval, int $intervalCount):SubscriptionPlan {
		Statistics::addCall("PayPal", "createSubscriptionPlan");
		$ip = new Interpreter(new Sandbox(PayPal::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $name, $amount, $currency, $description, $interval, $intervalCount];
		$ip->callFunctionSync('createSubscriptionPlan', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	
	/**
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return array
	 */
	public function listSubscriptionPlans():array {
		Statistics::addCall("PayPal", "listSubscriptionPlans");
		$ip = new Interpreter(new Sandbox(PayPal::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null];
		$ip->callFunctionSync('listSubscriptionPlans', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	
	/**
	 * @param string $planID
	 * @param string $name
	 * @param string $description
	 * @param CreditCard $source
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return Subscription
	 */
	public function createSubscription(string $planID, string $name, string $description, CreditCard $source):Subscription {
		Statistics::addCall("PayPal", "createSubscription");
		$ip = new Interpreter(new Sandbox(PayPal::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $planID, $name, $description, $source];
		$ip->callFunctionSync('createSubscription', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	
	/**
	 * @param string $id
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 */
	public function cancelSubscription(string $id):void {
		Statistics::addCall("PayPal", "cancelSubscription");
		$ip = new Interpreter(new Sandbox(PayPal::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, $id];
		$ip->callFunctionSync('cancelSubscription', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		
	}
	
	/**
	 * @param AdvancedRequestSpecification $specification
	 * @throws IllegalArgumentError
	 * @throws AuthenticationError
	 * @throws NotFoundError
	 * @throws HttpError
	 * @throws ServiceUnavailableError
	 * @throws \Exception
	 * @return AdvancedRequestResponse
	 */
	public function advancedRequest(AdvancedRequestSpecification $specification):AdvancedRequestResponse {
		Statistics::addCall("PayPal", "advancedRequest");
		$ip = new Interpreter(new Sandbox(PayPal::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		$auxArray = [$this->interpreterStorage, null, $specification];
		$ip->callFunctionSync('AdvancedRequestSupporter:advancedRequest', $auxArray);
		$error = $ip->getError();
		if (!is_null($error)) {
			if ($error->getType() == CloudRailError::ILLEGAL_ARGUMENT) {
				throw new IllegalArgumentError($error->getMessage());
			} else if ($error->getType() == CloudRailError::AUTHENTICATION) {
				throw new AuthenticationError($error->getMessage());
			} else if ($error->getType() == CloudRailError::NOT_FOUND) {
				throw new NotFoundError($error->getMessage());
			} else if ($error->getType() == CloudRailError::HTTP) {
				throw new HttpError($error->getMessage());
			} else if ($error->getType() == CloudRailError::SERVICE_UNAVAILABLE) {
				throw new ServiceUnavailableError($error->getMessage());
			} else {
				throw new \Exception($error->getMessage());
			}
		}
		return $ip->getParameter(1);
	}
	

	/**
	 * @return string
	 */
	public function saveAsString() {
		$ip = new Interpreter(new Sandbox(PayPal::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage));
		return $ip->saveAsString();
	}

	/**
	 * @param string $savedState
	 */
	public function loadAsString($savedState) {
		$sandbox = new Sandbox(PayPal::$SERVICE_CODE, $this->persistentStorage, $this->instanceDependencyStorage);
		$ip = new Interpreter($sandbox);
		$ip->loadAsString($savedState);
		$this->persistentStorage = $sandbox->persistentStorage;
	}
}
