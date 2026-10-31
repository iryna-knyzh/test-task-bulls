<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('sheets:sync')->dailyAt(config('sheets.daily_at'));
