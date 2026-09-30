-- Run after migrations as rental_owner; supply runtime_password via protected psql variable.
CREATE ROLE rental_runtime LOGIN PASSWORD :'runtime_password';
GRANT CONNECT ON DATABASE rental TO rental_runtime;
GRANT USAGE ON SCHEMA public TO rental_runtime;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO rental_runtime;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO rental_runtime;
REVOKE UPDATE, DELETE ON audit_events, ledger_entries, rental_versions, inspections, inspection_photos, receipts FROM rental_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE rental_owner IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO rental_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE rental_owner IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO rental_runtime;
