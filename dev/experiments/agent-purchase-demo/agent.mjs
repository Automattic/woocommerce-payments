import { readFile } from 'node:fs/promises';
const config = JSON.parse(await readFile(new URL('./.runtime/agent.json', import.meta.url), 'utf8'));
const base = new URL(config.baseUrl);
if (!['localhost', '127.0.0.1'].includes(base.hostname) || base.protocol !== 'http:') throw new Error('This demo only supports the local test store.');
const [command, value, quantity = '1'] = process.argv.slice(2);
const shippingAddress = {first_name: 'Demo', last_name: 'Shopper', address_1: '123 Demo Street', address_2: '', city: 'San Francisco', state: 'CA', postcode: '94107', country: 'US'};
const commands = {
  search: ['/products', 'GET'],
  quote: ['/quotes', 'POST', {product_id: Number(value), quantity: Number(quantity), shipping_address: shippingAddress}],
  status: [`/quotes/${value}`, 'GET'],
  complete: [`/quotes/${value}/complete`, 'POST'],
};
if (!commands[command]) {
  console.error('Usage: node agent.mjs search [term] | quote PRODUCT_ID [QUANTITY] | status QUOTE_ID | complete QUOTE_ID');
  process.exit(1);
}
const [path, method, body] = commands[command];
const response = await fetch(`${base.origin}/?rest_route=/wcpay/agent-purchases/v1${path}`, {
  method, headers: {'Content-Type': 'application/json', 'X-WCPay-Agent': config.key},
  ...(body ? {body: JSON.stringify(body)} : {}),
});
let result = await response.json();
if (command === 'search' && value && result.products) result.products = result.products.filter(p => p.name.toLowerCase().includes(value.toLowerCase()));
console.log(JSON.stringify(result, null, 2));
if (!response.ok) process.exitCode = 1;
