import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

let config;
try {
  config = JSON.parse(await readFile(new URL('./.runtime/agent.json', import.meta.url), 'utf8'));
} catch {
  config = { baseUrl: 'http://localhost:8082', key: 'not-configured' };
}
const shippingAddress = {first_name: 'Demo', last_name: 'Shopper', address_1: '123 Demo Street', address_2: '', city: 'San Francisco', state: 'CA', postcode: '94107', country: 'US'};
const root = `${config.baseUrl}/?rest_route=/wcpay/agent-purchases/v1`;
async function request(path, { auth = true, method = 'GET', body } = {}) {
  const response = await fetch(`${root}${path}`, {
    method,
    headers: { 'Content-Type': 'application/json', ...(auth ? { 'X-WCPay-Agent': config.key } : {}) },
    ...(body ? { body: JSON.stringify(body) } : {}),
  });
  return { status: response.status, body: await response.json() };
}

const products = await request('/products');
assert.equal(products.status, 200, 'An authenticated agent can discover real demo products.');
assert.ok(products.body.products.length > 0, 'The catalog contains a real product.');
assert.equal((await request('/products', { auth: false })).status, 403, 'Unauthenticated callers cannot use the demo API.');
const malformed = await request('/quotes', { method: 'POST', body: { product_id: [products.body.products[0].id], quantity: 1, shipping_address: {} } });
assert.equal(malformed.status, 400, 'Malformed product identifiers are rejected by the native schema.');
const missingAddress = await request('/quotes', { method: 'POST', body: { product_id: products.body.products[0].id, quantity: 1 } });
assert.equal(missingAddress.status, 400, 'A quote must include an explicit shipping address.');
const quoted = await request('/quotes', { method: 'POST', body: { product_id: products.body.products[0].id, quantity: 1, shipping_address: shippingAddress } });
assert.equal(quoted.status, 201, 'The store can create a quote.');
assert.equal(quoted.body.quote.subtotal, '12.00');
assert.equal(quoted.body.quote.shipping_total, '2.50');
assert.equal(quoted.body.quote.tax_total, '1.16');
assert.equal(quoted.body.quote.total, '15.66', 'WooCommerce calculates the product, shipping and eight-percent tax.');
assert.equal(quoted.body.state, 'awaiting_approval');
const unpaid = await request(`/quotes/${quoted.body.id}/complete`, { method: 'POST' });
assert.equal(unpaid.status, 409, 'The agent cannot pay before the shopper approves.');
const approve = await request(`/quotes/${quoted.body.id}/approve`, { method: 'POST' });
assert.equal(approve.status, 404, 'The agent API has no approval operation.');
console.log(JSON.stringify({ checks: 13, quote_id: quoted.body.id, approval_url: quoted.body.approval_url, total: quoted.body.quote.total }, null, 2));
