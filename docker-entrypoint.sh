#!/bin/bash
# Start cron in background
service cron start

# Start Apache in foreground
apache2-foreground
