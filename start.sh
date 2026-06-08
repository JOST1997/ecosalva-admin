#!/bin/sh
exec php -S 0.0.0.0:${PORT:-80} -t /app
