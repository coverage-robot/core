## Local Development

### Token
To create a new project token:
```makefile
make token provider=... owner=... repository=...
```

### Migrations

To make a new migration file:
```makefile
make migration
```

To execute any unapplied migrations:
```makefile
make migrate
```