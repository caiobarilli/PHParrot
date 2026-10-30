import assert from 'node:assert/strict';
import { test } from 'node:test';
import { xsrf } from './xsrf.js';

test('lê e decodifica o cookie do XSRF', () => {
    assert.equal(xsrf('a=1; XSRF-TOKEN=abc%3D%3D; b=2'), 'abc==');
});

test('sem o cookie devolve vazio', () => {
    assert.equal(xsrf('a=1'), '');
});
