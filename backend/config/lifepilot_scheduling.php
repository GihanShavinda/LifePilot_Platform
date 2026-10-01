<?php
return [
 'google'=>['client_id'=>env('GOOGLE_CALENDAR_CLIENT_ID'),'client_secret'=>env('GOOGLE_CALENDAR_CLIENT_SECRET'),'redirect_uri'=>env('GOOGLE_CALENDAR_REDIRECT_URI',env('APP_URL','http://localhost:8000').'/api/v1/calendar/google/callback')],
 'push'=>['provider'=>env('LIFEPILOT_PUSH_PROVIDER','disabled'),'vapid_public_key'=>env('VAPID_PUBLIC_KEY'),'vapid_private_key'=>env('VAPID_PRIVATE_KEY'),'vapid_subject'=>env('VAPID_SUBJECT')],
 'horizon_days'=>(int)env('CALENDAR_GENERATION_HORIZON_DAYS',45),
 'delivery_max_attempts'=>(int)env('NOTIFICATION_MAX_ATTEMPTS',3),
];
