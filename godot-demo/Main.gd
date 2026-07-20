extends Node3D
#
# Demo "Recolecta" — Metaverso Escolar TecNM
#
# Un mini-juego 3D minimísimo: te mueves con WASD sobre un piso y recoges monedas.
# Al juntarlas todas se muestra el puntaje. Todo el mundo 3D se construye por código
# en _ready(), así el proyecto es solo este script + una escena vacía que lo carga.
#
# GANCHO para conectar al backend después: cuando termines (_completar), en vez de
# solo mostrar el puntaje, harías las llamadas HTTP con un nodo HTTPRequest:
#   1) POST /api/game/redeem      (canjea el token del magic link -> Bearer)
#   2) POST /api/game/sessions    (inicia la sesión con id_evento)
#   3) POST /api/game/sessions/{id}/complete  (envía calificacion 0-100)
# Ver docs/api/flujo-del-juego.md en el repo. Aquí lo dejamos en modo demo.

const VELOCIDAD := 7.0
const TOTAL_MONEDAS := 6

var player: CharacterBody3D
var camara: Camera3D
var hud: Label
var mensaje_fin: Label
var monedas: Array[Area3D] = []
var recogidas := 0
var termino := false


func _ready() -> void:
	_crear_entorno()
	_crear_piso()
	player = _crear_player()
	_crear_camara()
	_crear_monedas()
	_crear_hud()
	_actualizar_hud()


func _crear_entorno() -> void:
	var mundo := WorldEnvironment.new()
	var env := Environment.new()
	env.background_mode = Environment.BG_COLOR
	env.background_color = Color(0.07, 0.11, 0.2)
	env.ambient_light_source = Environment.AMBIENT_SOURCE_COLOR
	env.ambient_light_color = Color(0.45, 0.5, 0.65)
	env.ambient_light_energy = 0.7
	mundo.environment = env
	add_child(mundo)

	var luz := DirectionalLight3D.new()
	luz.rotation_degrees = Vector3(-55, -40, 0)
	luz.light_energy = 1.3
	luz.shadow_enabled = true
	add_child(luz)


func _crear_piso() -> void:
	var cuerpo := StaticBody3D.new()

	var vista := MeshInstance3D.new()
	var caja := BoxMesh.new()
	caja.size = Vector3(24, 0.5, 24)
	vista.mesh = caja
	var mat := StandardMaterial3D.new()
	mat.albedo_color = Color(0.16, 0.22, 0.34)
	vista.material_override = mat
	cuerpo.add_child(vista)

	var col := CollisionShape3D.new()
	var forma := BoxShape3D.new()
	forma.size = Vector3(24, 0.5, 24)
	col.shape = forma
	cuerpo.add_child(col)

	cuerpo.position = Vector3(0, -0.25, 0)
	add_child(cuerpo)


func _crear_player() -> CharacterBody3D:
	var p := CharacterBody3D.new()

	var vista := MeshInstance3D.new()
	var capsula := CapsuleMesh.new()
	capsula.radius = 0.5
	capsula.height = 1.6
	vista.mesh = capsula
	vista.position = Vector3(0, 0.8, 0)
	var mat := StandardMaterial3D.new()
	mat.albedo_color = Color(0.25, 0.62, 0.95)
	vista.material_override = mat
	p.add_child(vista)

	var col := CollisionShape3D.new()
	var forma := CapsuleShape3D.new()
	forma.radius = 0.5
	forma.height = 1.6
	col.shape = forma
	col.position = Vector3(0, 0.8, 0)
	p.add_child(col)

	p.position = Vector3(0, 0.2, 0)
	add_child(p)
	return p


func _crear_camara() -> void:
	camara = Camera3D.new()
	camara.rotation_degrees = Vector3(-52, 0, 0)
	camara.position = Vector3(0, 13, 10)
	add_child(camara)


func _crear_monedas() -> void:
	var rng := RandomNumberGenerator.new()
	rng.randomize()
	for i in TOTAL_MONEDAS:
		var area := Area3D.new()

		var vista := MeshInstance3D.new()
		var cil := CylinderMesh.new()
		cil.top_radius = 0.4
		cil.bottom_radius = 0.4
		cil.height = 0.12
		vista.mesh = cil
		vista.rotation_degrees = Vector3(90, 0, 0)
		var mat := StandardMaterial3D.new()
		mat.albedo_color = Color(1.0, 0.82, 0.2)
		mat.emission_enabled = true
		mat.emission = Color(0.85, 0.6, 0.1)
		mat.emission_energy_multiplier = 0.6
		vista.material_override = mat
		area.add_child(vista)

		var col := CollisionShape3D.new()
		var forma := CylinderShape3D.new()
		forma.radius = 0.55
		forma.height = 1.2
		col.shape = forma
		area.add_child(col)

		# Reparto en el piso, sin caer justo encima del jugador.
		var x := rng.randf_range(-9.0, 9.0)
		var z := rng.randf_range(-9.0, 9.0)
		if abs(x) < 2.0 and abs(z) < 2.0:
			x += 4.0
		area.position = Vector3(x, 0.7, z)
		area.body_entered.connect(_on_moneda_tocada.bind(area))
		monedas.append(area)
		add_child(area)


func _crear_hud() -> void:
	var capa := CanvasLayer.new()
	add_child(capa)

	hud = Label.new()
	hud.add_theme_font_size_override("font_size", 26)
	hud.position = Vector2(24, 18)
	capa.add_child(hud)

	var ayuda := Label.new()
	ayuda.text = "WASD para moverte · recoge las monedas"
	ayuda.add_theme_font_size_override("font_size", 16)
	ayuda.modulate = Color(1, 1, 1, 0.7)
	ayuda.position = Vector2(24, 54)
	capa.add_child(ayuda)

	mensaje_fin = Label.new()
	mensaje_fin.add_theme_font_size_override("font_size", 44)
	mensaje_fin.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	mensaje_fin.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
	mensaje_fin.set_anchors_and_offsets_preset(Control.PRESET_FULL_RECT)
	mensaje_fin.visible = false
	capa.add_child(mensaje_fin)


func _actualizar_hud() -> void:
	hud.text = "Monedas: %d / %d" % [recogidas, TOTAL_MONEDAS]


func _physics_process(delta: float) -> void:
	if player == null:
		return

	var dir := Vector3.ZERO
	if Input.is_physical_key_pressed(KEY_W):
		dir.z -= 1.0
	if Input.is_physical_key_pressed(KEY_S):
		dir.z += 1.0
	if Input.is_physical_key_pressed(KEY_A):
		dir.x -= 1.0
	if Input.is_physical_key_pressed(KEY_D):
		dir.x += 1.0
	dir = dir.normalized()

	player.velocity.x = dir.x * VELOCIDAD
	player.velocity.z = dir.z * VELOCIDAD
	player.velocity.y -= 24.0 * delta  # gravedad, para que se quede pegado al piso
	player.move_and_slide()

	# La cámara sigue al jugador desde arriba y atrás.
	camara.position = player.position + Vector3(0, 13, 10)


func _process(delta: float) -> void:
	# Giro suave de las monedas para que se vean vivas.
	for m in monedas:
		if is_instance_valid(m):
			m.rotate_y(delta * 2.0)


func _on_moneda_tocada(cuerpo: Node, area: Area3D) -> void:
	if termino or cuerpo != player or not is_instance_valid(area):
		return
	monedas.erase(area)
	area.queue_free()
	recogidas += 1
	_actualizar_hud()
	if recogidas >= TOTAL_MONEDAS:
		_completar()


func _completar() -> void:
	termino = true
	var puntaje := 100  # todas recogidas = 100
	mensaje_fin.text = "¡Completado!\nCalificación: %d" % puntaje
	mensaje_fin.visible = true
	# GANCHO backend: aquí enviarías 'puntaje' a POST /api/game/sessions/{id}/complete.
	print("[demo] Práctica completada. Calificación a enviar: %d" % puntaje)
