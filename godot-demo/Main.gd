extends Node3D
#
# Demo "Recolecta" — Metaverso Escolar TecNM (Godot 4)
#
# Mini-juego 3D: WASD para moverte, recoge las monedas. Puede correr conectado al
# backend (canjea el token del magic link, inicia la sesión y al terminar envía la
# calificación de vuelta a Moodle) o en modo "sin conexión" para enseñarlo sin servidor.
#
# Flujo backend (ver docs/api/flujo-del-juego.md):
#   POST /api/game/redeem            {token}                  -> {access_token, evento:{id_evento}, ...}
#   POST /api/game/sessions          {id_evento}   (Bearer)   -> {id_sesion}
#   POST /api/game/sessions/{id}/complete {calificacion,...} (Bearer) -> ok
#
# Variables de entorno (opcionales, para prellenar/automatizar):
#   DEMO_URL    URL base del servidor (default http://127.0.0.1:8000)
#   DEMO_TOKEN  token del magic link, para prellenar el campo
#   DEMO_AUTO=1 conecta y completa solo (modo kiosco/prueba, sin jugar)

const VELOCIDAD := 7.0
const TOTAL_MONEDAS := 6

# --- estado de juego ---
var player: CharacterBody3D
var camara: Camera3D
var hud: Label
var mensaje_fin: Label
var monedas: Array[Area3D] = []
var recogidas := 0
var jugando := false
var termino := false

# --- estado de conexión ---
var base_url := ""
var bearer := ""
var id_evento := 0
var id_sesion := 0
var sin_backend := false

# --- UI de inicio ---
var panel_inicio: Control
var campo_url: LineEdit
var campo_token: LineEdit
var boton_conectar: Button
var etiqueta_estado: Label


func _ready() -> void:
	_crear_entorno()
	_crear_piso()
	_crear_camara()
	_crear_hud()
	_crear_inicio()

	if OS.get_environment("DEMO_AUTO") == "1":
		await get_tree().process_frame
		_conectar()


# ============================ CONEXIÓN ============================

func _crear_inicio() -> void:
	var capa := CanvasLayer.new()
	capa.layer = 10
	add_child(capa)

	panel_inicio = PanelContainer.new()
	panel_inicio.set_anchors_and_offsets_preset(Control.PRESET_CENTER)
	panel_inicio.custom_minimum_size = Vector2(460, 0)
	capa.add_child(panel_inicio)

	var caja := VBoxContainer.new()
	caja.add_theme_constant_override("separation", 12)
	panel_inicio.add_child(caja)

	var titulo := Label.new()
	titulo.text = "Metaverso — Demo Recolecta"
	titulo.add_theme_font_size_override("font_size", 26)
	caja.add_child(titulo)

	caja.add_child(_etiqueta("Servidor"))
	campo_url = LineEdit.new()
	var url_env := OS.get_environment("DEMO_URL")
	campo_url.text = url_env if url_env != "" else "http://127.0.0.1:8000"
	caja.add_child(campo_url)

	caja.add_child(_etiqueta("Token del enlace de acceso"))
	campo_token = LineEdit.new()
	campo_token.placeholder_text = "pega aquí el token del magic link"
	campo_token.text = OS.get_environment("DEMO_TOKEN")
	caja.add_child(campo_token)

	boton_conectar = Button.new()
	boton_conectar.text = "Conectar y jugar"
	boton_conectar.pressed.connect(_conectar)
	caja.add_child(boton_conectar)

	var boton_offline := Button.new()
	boton_offline.text = "Jugar sin conexión"
	boton_offline.pressed.connect(_jugar_offline)
	caja.add_child(boton_offline)

	etiqueta_estado = Label.new()
	etiqueta_estado.autowrap_mode = TextServer.AUTOWRAP_WORD_SMART
	etiqueta_estado.modulate = Color(1, 0.8, 0.4)
	caja.add_child(etiqueta_estado)


func _etiqueta(texto: String) -> Label:
	var l := Label.new()
	l.text = texto
	l.add_theme_font_size_override("font_size", 14)
	l.modulate = Color(1, 1, 1, 0.75)
	return l


func _estado(texto: String) -> void:
	if etiqueta_estado:
		etiqueta_estado.text = texto


func _headers_json() -> PackedStringArray:
	return PackedStringArray(["Content-Type: application/json", "Accept: application/json"])


func _headers_auth() -> PackedStringArray:
	var h := _headers_json()
	h.append("Authorization: Bearer " + bearer)
	return h


# Hace una petición HTTP y espera la respuesta. Devuelve {ok, code, body(Dictionary), error}.
func _api(metodo: int, url: String, headers: PackedStringArray, cuerpo) -> Dictionary:
	var http := HTTPRequest.new()
	add_child(http)
	var body_str := "" if cuerpo == null else JSON.stringify(cuerpo)
	var err := http.request(url, headers, metodo, body_str)
	if err != OK:
		http.queue_free()
		return {"ok": false, "code": 0, "body": {}, "error": "no se pudo iniciar la petición (%d)" % err}
	var res: Array = await http.request_completed
	http.queue_free()
	var resultado: int = res[0]
	var code: int = res[1]
	var raw: String = (res[3] as PackedByteArray).get_string_from_utf8()
	var body := {}
	if raw != "":
		var parsed = JSON.parse_string(raw)
		if typeof(parsed) == TYPE_DICTIONARY:
			body = parsed
	if resultado != HTTPRequest.RESULT_SUCCESS:
		return {"ok": false, "code": code, "body": body, "error": "fallo de red (%d)" % resultado}
	return {"ok": code >= 200 and code < 300, "code": code, "body": body, "error": ""}


func _mensaje_api(r: Dictionary) -> String:
	if r.get("error", "") != "":
		return r["error"]
	var body: Dictionary = r.get("body", {})
	return str(body.get("message", "sin detalle"))


func _conectar() -> void:
	base_url = campo_url.text.strip_edges().trim_suffix("/")
	var token := campo_token.text.strip_edges()
	if token == "":
		_estado("Pega el token del enlace de acceso.")
		return
	boton_conectar.disabled = true
	_estado("Canjeando token…")

	var r := await _api(HTTPClient.METHOD_POST, base_url + "/api/game/redeem", _headers_json(), {"token": token})
	if not r.ok:
		_estado("Redeem falló (%d): %s" % [r.code, _mensaje_api(r)])
		boton_conectar.disabled = false
		return
	bearer = str(r.body.get("access_token", ""))
	var evento: Dictionary = r.body.get("evento", {})
	id_evento = int(evento.get("id_evento", 0))

	_estado("Iniciando sesión…")
	var s := await _api(HTTPClient.METHOD_POST, base_url + "/api/game/sessions", _headers_auth(), {"id_evento": id_evento})
	if not s.ok:
		_estado("No se pudo iniciar la sesión (%d): %s" % [s.code, _mensaje_api(s)])
		boton_conectar.disabled = false
		return
	id_sesion = int(s.body.get("id_sesion", 0))

	print("[demo] conectado. id_evento=%d id_sesion=%d" % [id_evento, id_sesion])
	_iniciar_juego()

	if OS.get_environment("DEMO_AUTO") == "1":
		await get_tree().create_timer(0.3).timeout
		_completar()


func _jugar_offline() -> void:
	sin_backend = true
	_iniciar_juego()


func _iniciar_juego() -> void:
	panel_inicio.get_parent().queue_free()  # quita la CanvasLayer de inicio
	player = _crear_player()
	_crear_monedas()
	jugando = true
	_actualizar_hud()


# ============================ MUNDO 3D ============================

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
	mensaje_fin.add_theme_font_size_override("font_size", 40)
	mensaje_fin.horizontal_alignment = HORIZONTAL_ALIGNMENT_CENTER
	mensaje_fin.vertical_alignment = VERTICAL_ALIGNMENT_CENTER
	mensaje_fin.set_anchors_and_offsets_preset(Control.PRESET_FULL_RECT)
	mensaje_fin.visible = false
	capa.add_child(mensaje_fin)


func _actualizar_hud() -> void:
	if hud:
		hud.text = "Monedas: %d / %d" % [recogidas, TOTAL_MONEDAS]


# ============================ JUEGO ============================

func _physics_process(delta: float) -> void:
	if not jugando or player == null:
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
	player.velocity.y -= 24.0 * delta
	player.move_and_slide()

	camara.position = player.position + Vector3(0, 13, 10)


func _process(delta: float) -> void:
	for m in monedas:
		if is_instance_valid(m):
			m.rotate_y(delta * 2.0)


func _on_moneda_tocada(cuerpo: Node, area: Area3D) -> void:
	if termino or not jugando or cuerpo != player or not is_instance_valid(area):
		return
	monedas.erase(area)
	area.queue_free()
	recogidas += 1
	_actualizar_hud()
	if recogidas >= TOTAL_MONEDAS:
		_completar()


func _completar() -> void:
	if termino:
		return
	termino = true
	jugando = false
	var puntaje := 100

	if sin_backend:
		mensaje_fin.text = "¡Completado!\nCalificación: %d" % puntaje
		mensaje_fin.visible = true
		print("[demo] completado sin backend. Calificación: %d" % puntaje)
		return

	mensaje_fin.text = "¡Completado!\nEnviando calificación…"
	mensaje_fin.visible = true

	var c := await _api(
		HTTPClient.METHOD_POST,
		base_url + "/api/game/sessions/%d/complete" % id_sesion,
		_headers_auth(),
		{"calificacion": puntaje, "datos_resultado": {"fuente": "godot-demo", "monedas": TOTAL_MONEDAS}},
	)
	if c.ok:
		mensaje_fin.text = "¡Completado!\nCalificación %d enviada ✔" % puntaje
		print("[demo] complete OK: ", c.body)
	else:
		mensaje_fin.text = "Juego completado (%d)\nError al enviar: %s" % [puntaje, _mensaje_api(c)]
		print("[demo] complete FALLÓ code=%d: %s" % [c.code, str(c.body)])

	if OS.get_environment("DEMO_AUTO") == "1":
		await get_tree().create_timer(0.5).timeout
		get_tree().quit()
