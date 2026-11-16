<?php
session_start();

const APP_NAME = 'Campus Exchange';
const DB_HOST = '127.0.0.1';
const DB_NAME = 'campus_ecommerce';
const DB_USER = 'root';
const DB_PASS = '';
const SQLITE_DB_PATH = __DIR__ . '/data/campus_demo.sqlite';

if (!is_dir(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0777, true);
}
