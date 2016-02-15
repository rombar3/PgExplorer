cd /d "C:\Program Files\PostgreSQL\9.2\bin"
psql.exe -U postgres -d postgres -f E:\VMShare\pagila-0.10.1\init-database.sql
psql.exe -U barbu -d pagila -f E:\VMShare\pagila-0.10.1\pagila-schema.sql
cd /D E:\wamp\www\PgExplorer
type NUL > app\logs\dev.log