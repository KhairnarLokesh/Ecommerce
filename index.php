<?php
/**
 * Kalika Enterprises - Root Redirect
 * Automatically forwards root requests to the customer storefront in /public/
 */
header('Location: /public/', true, 302);
exit;
