sequenceDiagram
    actor Alumno
    participant Portal as Portal web
    participant API as Backend API
    participant BD as Base de datos

    Alumno->>Portal: Abre su calendario
    Portal->>API: GET /slots?practica=X
    API->>BD: Consulta slots y cuenta reservas activas
    BD-->>API: Slots con lugares disponibles
    API-->>Portal: Lista de slots (cupo restante)
    Portal-->>Alumno: Muestra slots disponibles

    Alumno->>Portal: Selecciona un slot y confirma
    Portal->>API: POST /reservas {id_slot}

    API->>BD: BEGIN (inicia transacción)
    API->>BD: Verifica inscripción en el grupo
    API->>BD: SELECT ... FOR UPDATE (bloquea el slot) y cuenta reservas
    Note over API,BD: El bloqueo evita que dos alumnos<br/>tomen el último lugar al mismo tiempo

    alt No inscrito en el grupo
        API->>BD: ROLLBACK
        API-->>Portal: 403 No inscrito
        Portal-->>Alumno: "No estás inscrito en este grupo"
    else Cupo lleno (reservas >= límite)
        API->>BD: ROLLBACK
        API-->>Portal: 409 Slot lleno
        Portal-->>Alumno: "Slot lleno, elige otro horario"
    else Cupo disponible
        API->>BD: INSERT reserva
        API->>BD: COMMIT (libera el bloqueo)
        API-->>Portal: 201 Reserva confirmada
        Portal-->>Alumno: "Reserva confirmada"
    end