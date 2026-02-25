<?php

use Illuminate\Support\Facades\Route;
use OpenCompany\Chatogrator\Http\ChatWebhookController;

Route::post('{adapter}', ChatWebhookController::class);
