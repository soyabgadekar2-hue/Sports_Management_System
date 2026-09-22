<?php
declare(strict_types=1);

/**
 * Application-wide settings that contain no credentials.
 */
const APP_ENVIRONMENT = 'development';
const APP_TIMEZONE = 'Asia/Kolkata';

date_default_timezone_set(APP_TIMEZONE);
