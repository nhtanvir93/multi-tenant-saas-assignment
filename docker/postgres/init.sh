#!/bin/sh
# Runs once, on first creation of the postgres volume. Creates the database used by the test suite.
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres <<-EOSQL
    CREATE DATABASE app_testing;
EOSQL
