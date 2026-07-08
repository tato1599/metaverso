erDiagram
    ROL ||--o{ USUARIO : tiene
    USUARIO ||--o| ALUMNO : es
    USUARIO ||--o| MAESTRO : es
    USUARIO ||--o{ TOKEN_JUEGO : genera

    CARRERA ||--o{ ALUMNO : pertenece
    CARRERA ||--o{ MATERIA_CARRERA : incluye
    MATERIA ||--o{ MATERIA_CARRERA : se_imparte_en

    CICLO_ESCOLAR ||--o{ GRUPO : contiene
    MATERIA ||--o{ GRUPO : se_abre_como
    MAESTRO ||--o{ GRUPO : imparte

    ALUMNO ||--o{ INSCRIPCION : realiza
    GRUPO ||--o{ INSCRIPCION : recibe

    MATERIA ||--o{ PRACTICA : define
    PRACTICA ||--o{ EVENTO_AGENDA : se_agenda_en
    GRUPO ||--o{ EVENTO_AGENDA : asignado_a
    ESPACIO ||--o{ EVENTO_AGENDA : ocurre_en

    EVENTO_AGENDA ||--o{ SESION_PRACTICA : registra
    ALUMNO ||--o{ SESION_PRACTICA : ejecuta
    PRACTICA ||--o{ SESION_PRACTICA : evalua
    EVENTO_AGENDA ||--o{ TOKEN_JUEGO : autoriza

    ROL {
        int id_rol PK
        string nombre "Alumno, Maestro, Coordinador, Admin"
        string descripcion
    }
    USUARIO {
        int id_usuario PK
        int id_rol FK
        string correo UK
        string contrasena_hash
        string nombre
        string apellidos
        bool activo
        datetime fecha_creacion
    }
    ALUMNO {
        int id_alumno PK
        int id_usuario FK
        int id_carrera FK
        string matricula UK
        int semestre_actual
        string generacion
    }
    MAESTRO {
        int id_maestro PK
        int id_usuario FK
        string numero_empleado UK
        string grado_academico
        string especialidad
    }
    CARRERA {
        int id_carrera PK
        string clave UK
        string nombre
        int duracion_semestres
    }
    MATERIA {
        int id_materia PK
        string clave UK
        string nombre
        int creditos
    }
    MATERIA_CARRERA {
        int id_materia_carrera PK
        int id_materia FK
        int id_carrera FK
        int semestre "en que semestre de esa carrera"
    }
    CICLO_ESCOLAR {
        int id_ciclo PK
        string nombre "2026-1, 2026-2..."
        date fecha_inicio
        date fecha_fin
        bool activo
    }
    GRUPO {
        int id_grupo PK
        int id_materia FK
        int id_maestro FK
        int id_ciclo FK
        string clave "3A, 5B..."
        int cupo_maximo
    }
    INSCRIPCION {
        int id_inscripcion PK
        int id_alumno FK
        int id_grupo FK
        date fecha_inscripcion
        string estatus
    }
    PRACTICA {
        int id_practica PK
        int id_materia FK
        string titulo
        string descripcion
        string objetivos
        int duracion_estimada
        int orden
        string escena_referencia "id de nivel/escena, agnostico al motor"
    }
    ESPACIO {
        int id_espacio PK
        string nombre
        string tipo "fisico / virtual"
        int capacidad
    }
    EVENTO_AGENDA {
        int id_evento PK
        int id_practica FK
        int id_grupo FK
        int id_espacio FK
        datetime fecha_hora_inicio
        datetime fecha_hora_fin
        string estatus "programado, en_curso, finalizado, cancelado"
    }
    SESION_PRACTICA {
        int id_sesion PK
        int id_evento FK
        int id_alumno FK
        int id_practica FK
        datetime fecha_inicio
        datetime fecha_fin
        string estatus "en_progreso, completada, abandonada"
        float calificacion
        json datos_resultado "telemetria cruda del juego"
    }
    TOKEN_JUEGO {
        int id_token PK
        int id_usuario FK
        int id_evento FK
        string token UK
        string plataforma "unreal, web, etc."
        datetime fecha_expiracion
        bool usado
        string ip_origen
    }