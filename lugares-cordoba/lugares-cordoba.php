<?php
/*
Plugin Name: Lugares Cordoba
Plugin URI: https://github.com/ModernizacionMuniCBA/
Description: Este plugin genera una plantilla para incluir en una p&aacute;gina un listado de lugares donde se realizan actividades.
Version: 1.5.3
Author: Ignacio Perlo
Author URI: https://github.com/
*/

setlocale(LC_ALL,"es_ES");
date_default_timezone_set('America/Argentina/Cordoba');

add_action('plugins_loaded', array('LugaresCordoba', 'get_instancia'));

class LugaresCordoba
{
	public static $instancia = null;

	private static $MESES = array("Ene", "Abr", "Ago", "Dic");
	private static $MONTHS = array("Jan", "Apr", "Aug", "Dec");

	private static $META_KEY_COLOR = 'color-buscador';
	private static $META_KEY_LOGO = 'logo-buscador';
	private static $META_KEY_AUDIENCIA = 'audiencia-buscador';

	private static $URL_API_GOB_AB = 'https://gobiernoabierto.cordoba.gob.ar/api';
	private static $ID_AUDIENCIA_CULTURA = 4;

	private static $IMAGEN_PREDETERMINADA_BUSCADOR = '/images/evento-predeterminado.png';
	private static $IMAGEN_PREDETERMINADA_LISTADO = '/images/listado-default.png';

	protected $plantillas;

	public $nonce_busquedas = '';
	public $nonce_reiniciar_opciones = '';

	public static function get_instancia() {
		if (null == self::$instancia) {
			self::$instancia = new LugaresCordoba();
		} 
		return self::$instancia;
	}

	private function __construct()
	{
		$this->plantillas = array();
		// Agrega un filtro al metabox de atributos para inyectar la plantilla en el cache.
		if (version_compare(floatval(get_bloginfo('version')), '4.7', '<')) { // 4.6 y anteriores
			add_filter(
				'page_attributes_dropdown_pages_args',
				array($this, 'registrar_plantillas')
			);
		} else { // Version 4.7
				add_filter(
					'theme_page_templates', array($this, 'agregar_plantilla_nueva')
				);
		}
		// Agregar un filtro al save post para inyectar plantilla al cache de la página
		add_filter(
			'wp_insert_post_data', 
			array($this, 'registrar_plantillas') 
		);
		// Agrega un filtro al template include para determinar si la página tiene la plantilla 
		// asignada y devolver su ruta
		add_filter(
			'template_include', 
			array($this, 'ver_plantillas') 
		);
		// Agrega plantillas al arreglo
		$this->plantillas = array(
			'buscador-lugares.php' => 'Buscador de Lugares',
		);
		
		add_action('wp_ajax_buscar_lugares', array($this, 'buscar_lugares')); 
		add_action('wp_ajax_nopriv_buscar_lugares', array($this, 'buscar_lugares'));
		add_action('wp_ajax_reiniciar_opciones', array($this, 'reiniciar_opciones')); 
		add_action('wp_ajax_nopriv_reiniciar_opciones', array($this, 'reiniciar_opciones'));
		add_action('wp_enqueue_scripts', array($this, 'cargar_assets'));
		add_action('template_redirect', array($this, 'buscador_template_redirect'));
		//add_action('add_meta_boxes_page', array($this, 'agregar_meta_box_lugares'));
		//add_action('admin_enqueue_styles', array($this, 'cargar_estilos_admin'));
		//add_action('admin_enqueue_scripts', array($this, 'cargar_scripts_admin'));
		//add_action('save_post', array($this, 'save_custom_meta_data'));

		add_shortcode('lista_lugares', array($this, 'render_shortcode_lista_lugares_cordoba'));
		add_action('init', array($this, 'boton_shortcode_lista_lugares'));
	}

	public function render_shortcode_lista_lugares_cordoba($atributos = [], $content = null, $tag = '')
	{
	    $atributos = array_change_key_case((array)$atributos, CASE_LOWER);
	    $atr = shortcode_atts([
            'categoria' => 0,
            'audiencia' => 0,
			'circuito' => 0,
			'estrellas' => 0,
			'orden' => '',
			'q'=>'',
            'cant' => '',
			'titulo' => '',
			'link' => ''
		], $atributos, $tag);
		
		$filtro_categoria = $atr['categoria'] == 0 ? '' : '&categorias_id='.$atr['categoria'];
	    $filtro_audiencia = $atr['audiencia'] == 0 ? '' : '&audiencia_id='.$atr['actividad'];
		$filtro_circuito = $atr['circuito'] == 0 ? '' : '&circuito_id='.$atr['circuito'];
		$filtro_estrellas = $atr['estrellas'] == 0 ? '' : '&estrellas='.$atr['estrellas'];
		$orden = $atr['orden'] == '' ? '' : '&orden='.$atr['orden'];
		$filtro_q = $atr['q'] == '' ? '' : '&q='.$atr['q'];
		$filtro_cantidad = $atr['cant'] == 0 ? '' : '&page_size='.$atr['cant'];
		$limitado = $atr['cant'] != 0;
		
		
		

	    $url = self::$URL_API_GOB_AB.'/lugar-actividad/?a=0'.$filtro_categoria.$filtro_audiencia.$filtro_circuito.$filtro_estrellas.$orden.$filtro_q.$filtro_cantidad;
		//echo $url;

    	$api_response = wp_remote_get($url);
    	//$nombre_transient = 'actividades_disciplina_' . $atr['disciplina'];
		$resultado = $this->chequear_respuesta($api_response, 'las disciplinas', $nombre_transient);

		$sc = '<div id="listado-actividades" class="c-cultura">';
		if ($atr['nombre']) {
			$link = '';
			$next = '<span class="c-next"></span>';
			if ($atr['link']) {
				$link = ' link="url:'.$atr['link'].'|||"';
				$next = '<a class="c-next__link" href="'.$atr['link'].'" title="Ver todas"><span class="c-next"></span></a>';
			}
			
			$sc .= '<span class="c-titulo">';
			$sc .= do_shortcode('[vc_custom_heading text="'.$atr['nombre'].'" font_container="tag:h3|font_size:20|text_align:left|color:%23262626|line_height:64px" google_fonts="font_family:Montserrat%3Aregular%2C700|font_style:400%20regular%3A400%3Anormal"'.$link.']');
			$sc .= '</span>'.$next;
		}
		$sc .= '<div class="c-actividades">';

		if (count($resultado['results']) > 0) {
			$sc .= '<ul>';
			foreach ($resultado['results'] as $key => $lugar) {
				
				//$url_disciplina = !empty($actividad['disciplinas']) ? $this->friendly_url($actividad['disciplinas'][0]['nombre']) : '';
				$url_evento = 'http://'.$_SERVER['SERVER_NAME'].'/lugar/'.$lugar['id'].'/'.$this->friendly_url($lugar['nombre']).'/';
				
				$imagen = '';
				$estilo = '';
				
				//echo "asdad".$actividad['fotos'][0]['foto']['original'];
				if ($lugar['fotos'][0]['foto']['thumbnail_350x350'] != '') {
					$imagen = $lugar['fotos'][0]['foto']['thumbnail_350x350'];
					$estilo = 'object-fit: cover;';
				} else {
					$imagen = plugins_url(self::$IMAGEN_PREDETERMINADA_BUSCADOR, __FILE__);
				}
				
				
					
				$sc .=	'<li class="o-actividad">'.
						'<a href="'.$url_evento.'"><div class="o-actividad__imagen" style="height:225px">
							<img with="300px" height="225px"  alt="'.$lugar['nombre'].'" style="width:100%;height:225px;'.$estilo.'" src="'.$imagen.'">
						</div></a>
						<div class="o-actividad__informacion">'.
						'<div class="o-actividad__cabecera"><a href="'.$url_evento.'" class="o-actividad__titulo">'.$lugar['nombre'].'</a></div>'.
						'<p class="o-actividad__fecha-actividad"></p>'.
						'<div class="o-actividad__descripcion">'.$lugar['direccion'].'</div>'.
						'</li>';
			}
			$sc .= '</ul>
			
			<script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-lazyload/10.0.1/lazyload.min.js"></script>';
			
				
			
		} else {
			$sc .= '<div class="c-resultado">No se encontraron lugares.</div>';
		}
		$sc .= '</div></div>';
		return $sc;
	}

		public function boton_shortcode_lista_lugares() {
		if (!current_user_can('edit_posts') && !current_user_can('edit_pages') && get_user_option('rich_editing') == 'true')
			return;

		add_filter("mce_external_plugins", array($this, "registrar_tinymce_plugin")); 
		add_filter('mce_buttons', array($this, 'agregar_boton_tinymce_shortcode_lista_lugares'));
	}

	public function registrar_tinymce_plugin($plugin_array) {
		$plugin_array['lugares_button'] = $this->cargar_url_asset('/js/shortcode.js');
	    return $plugin_array;
	}

	public function agregar_boton_tinymce_shortcode_lista_lugares($buttons) {
	    $buttons[] = "lugares_button";
	    return $buttons;
	}
	
	/**
	 * Se fija si la plantilla está asignada a la página
	 */
	

	public function buscador_template_redirect()
	{
		if (is_page_template('buscador-lugares.php')) {
			$_POST["datos"] = $this->obtener_datos_para_buscador();
			$_POST['URL_PLUGIN'] = plugins_url('' , __FILE__);
		}
	}

	public function cargar_assets()
	{
		$urlCSSShortcode = $this->cargar_url_asset('/css/shortcode.css');
		$urlCSSBuscador = $this->cargar_url_asset('/css/buscador.css');
		$urlJSBuscador = $this->cargar_url_asset('/js/buscador.js');

		wp_register_style('listado_lugares.css', $urlCSSShortcode);
		wp_register_style('buscador_lugares.css', $urlCSSBuscador);
		wp_register_script('buscador_lugares.js', $urlJSBuscador);

		if (is_page_template('buscador-lugares-template.php')) {

			wp_enqueue_style('shortcode_lugares.css', $urlCSSShortcode);
			wp_enqueue_style('buscador_lugares.css', $urlCSSBuscador);

			wp_enqueue_script(
				'buscar_lugares_ajax', 
				$urlJSBuscador, 
				array('jquery'), 
				'1.0.0',
				TRUE 
			);

			$nonce_busquedas = wp_create_nonce("buscar_lugares_nonce");

			global $post;
			$audiencia_buscador = get_post_meta($post->ID, 'audiencia-lugar', true);
			
			wp_localize_script(
				'buscar_lugares_ajax', 
				'buscarlugares', 
				array(
					'url'   => admin_url('admin-ajax.php'),
					'nonce' => $nonce_busquedas,
					'imagen' => plugins_url(self::$IMAGEN_PREDETERMINADA_BUSCADOR, __FILE__),
					'audiencia' => $audiencia_buscador
				)
			);
		} else {
			wp_enqueue_style('listado_lugares.css', $urlCSSShortcode);
		}
	}

	

	public function obtener_datos_para_buscador()
	{
		$datos = [];

		// Se buscan los datos en cache, sino se llama a la api

		$datos['audiencias'] = $this->buscar_transient('audiencias');

		$datos['disciplinas'] = $this->buscar_transient('disciplinas');

		$datos['tipo_actividad'] = $this->buscar_transient('tipo_actividad');

		$datos['lugares'] = $this->buscar_transient('lugares');

		$datos['actividades'] = $this->buscar_transient('actividades');

		$primerDiaSemana = date("Y-m-d", strtotime('monday this week'));
		$ultimoDiaSemana = date("Y-m-d", strtotime('sunday this week'));
		$primerDiaMes = date("Y-m-d", strtotime('first day of this month'));
		$ultimoDiaMes = date("Y-m-d", strtotime('last day of this month'));
		
		foreach($datos['actividades']['results'] as $key => $ac) {

			$inicia = date("Y-m-d", strtotime($ac['inicia']));
			$termina = date("Y-m-d", strtotime($ac['termina']));
			
			if ((($primerDiaSemana >= $inicia) && ($primerDiaSemana <= $termina)) || (($ultimoDiaSemana >= $inicia) && ($ultimoDiaSemana <= $termina))) {
				// Semana en rango
				$datos['actividades']['results'][$key]['rango_fecha'] = 'semana|mes|';
			} else if ((($primerDiaMes <= $inicia) && ($primerDiaMes >= $termina)) || (($ultimoDiaMes <= $inicia) && ($ultimoDiaMes >= $termina))) {
				$datos['actividades']['results'][$key]['rango_fecha'] = 'mes|';
			} else {
				$datos['actividades']['results'][$key]['rango_fecha'] = 'ninguno|';
			}
			
			$nombre = $ac['titulo'];
			if (strlen($nombre)>25) {
				$nombre = $this->quitar_palabras($ac['titulo'],5);
			} else {
				$nombre = $this->quitar_palabras($ac['titulo'],8);
			}
			$datos['actividades']['results'][$key]['nombre_corto'] = $nombre;
			
			$img = $ac['imagen'] ? $ac['imagen']['original'] : false;

			$imagen_final = !$img ? ($ac['flyer'] ? $ac['flyer']['thumbnail_400x400'] : plugins_url(self::$IMAGEN_PREDETERMINADA_BUSCADOR, __FILE__)) : $img;
			
			$datos['actividades']['results'][$key]['imagen_final'] = $imagen_final;
			
			$datos['actividades']['results'][$key]['descripcion'] = $this->quitar_palabras($ac['descripcion'], 20);
			
			if ($ac['inicia']) {
				$iniciaFormat = $this->formatear_fecha_tres_caracteres($ac['inicia']);
				$terminaFormat = $ac['termina'] ? $this->formatear_fecha_tres_caracteres($ac['termina']) : $iniciaFormat;
				
				$datos['actividades']['results'][$key]['fecha_actividad'] = $iniciaFormat == $terminaFormat ? $iniciaFormat : $iniciaFormat . ' / ' . $terminaFormat;
			}
			
			// Cadena con los ids de los tipos de la actividad.
			$ids = "";
			foreach ($ac['tipos'] as $keyTipo => $tipo) {
				$ids .= $tipo["id"]."|";
			}
			$datos['actividades']['results'][$key]['tipos_ids'] = $ids;
			
			// Cadena con los ids de las audiencias.
			$ids = "";
			foreach ($ac['audiencias'] as $keyDisc => $disc) {
				$ids .= $disc["id"]."|";
			}
			$datos['actividades']['results'][$key]['audiencias_ids'] = $ids;

			// Cadena con los ids de los tipos de la actividad.
			$ids = "";
			foreach ($ac['disciplinas'] as $keyDisc => $disc) {
				$ids .= $disc["id"]."|";
			}
			$datos['actividades']['results'][$key]['disciplinas_ids'] = $ids;
		}
		
		return $datos;
	}
	
	public function agregar_plantilla_nueva($posts_plantillas)
	{
		$posts_plantillas = array_merge($posts_plantillas, $this->plantillas);
		return $posts_plantillas;
	}

	public function registrar_plantillas($atts)
	{
		// Crea la key usada para el cacheo de temas
		$cache_key = 'page_templates-' . md5(get_theme_root() . '/' . get_stylesheet());
		// Recuperar la lista de caché. 
		// Si no existe o está vacío, prepara un arreglo
		$plantillas = wp_get_theme()->get_page_templates();
		empty($plantillas) && $plantillas = array();
		// Nuevo caché, por lo que se borra el anterior
		wp_cache_delete($cache_key , 'themes');
		// Agrega las plantillas nuevas a la lista fusionándolas
		// con el arreglo de plantillas del caché.
		$plantillas = array_merge($plantillas, $this->plantillas);
		// Se agrega el caché modificado para permitir que WordPress lo tome para
		// listar las plantillas disponibles
		wp_cache_add($cache_key, $plantillas, 'themes', 1800);
		return $atts;
	}

	/**
	 * Se fija si la plantilla está asignada a la página
	 */
	public function ver_plantillas($plantilla)
	{
		global $post;
		
		if (! $post) {
			return $plantilla;
		}
		
		if (!isset($this->plantillas[get_post_meta(
			$post->ID, '_wp_page_template', true 
		)])) {
			return $plantilla;
		} 
		$archivo = plugin_dir_path(__FILE__). get_post_meta(
			$post->ID, '_wp_page_template', true
		);
		
		if (file_exists($archivo)) {
			return $archivo;
		} else {
			echo $archivo;
		}
		
		return $plantilla;
	}
	
	/*
	* Mira si la respuesta es un error, si no lo es, cachea por una hora el resultado.
	*/
	private function chequear_respuesta($api_response, $tipoObjeto, $nombre_transient)
	{
		if (is_null($api_response)) {
			return [ 'results' => [] ];
		} else if (is_wp_error($api_response)) {
			$mensaje = WP_DEBUG ? ' '.$this->mostrar_error($api_response) : '';
			return [ 'results' => [], 'error' => 'Ocurri&oacute; un error al cargar '.$tipoObjeto.'.'.$mensaje];
		} else {
			$respuesta = json_decode(wp_remote_retrieve_body($api_response), true);
			set_site_transient('api_muni_cba_'.$nombre_transient, $respuesta, HOUR_IN_SECONDS);
			return $respuesta;
		}
	}

	/*private function buscar_transient($nombre_transient)
	{
		$transient = get_site_transient('api_muni_cba_'.$nombre_transient);
		$transient = [];
		global $post;
		$audiencia_buscador = get_post_meta($post->ID, self::$META_KEY_AUDIENCIA, true);
		$audiencia_buscador_param = $audiencia_buscador && $audiencia_buscador > 0 ? '?audiencia_id='.$audiencia_buscador : '';
		$api_response = null;
		$resultado = null;
		if(!empty($transient)) {
			return $transient;
		} else {
			switch($nombre_transient) {
				case 'audiencias': {
					$api_response = wp_remote_get(self::$URL_API_GOB_AB.'/audiencia-actividad/');
					$resultado = $this->chequear_respuesta($api_response, 'las audiencias', $nombre_transient);
				} break;
				case 'disciplinas': {
					$api_response = wp_remote_get(self::$URL_API_GOB_AB.'/disciplina-actividad/'.$audiencia_buscador_param);
					$resultado = $this->chequear_respuesta($api_response, 'las disciplinas', $nombre_transient);
				} break;
				case 'tipo_actividad': {
					$api_response = wp_remote_get(self::$URL_API_GOB_AB.'/tipo-actividad/'.$audiencia_buscador_param);
					$resultado = $this->chequear_respuesta($api_response, 'los tipos de actividad', $nombre_transient);
				} break;
				case 'lugares': {
					$api_response = wp_remote_get(self::$URL_API_GOB_AB.'/lugar-actividad/'.$audiencia_buscador_param);
					$resultado = $this->chequear_respuesta($api_response, 'los lugares', $nombre_transient);
				} break;
				case 'actividades': {
					$api_response = wp_remote_get(self::$URL_API_GOB_AB.'/actividad-publica/'.($audiencia_buscador_param != '' ? $audiencia_buscador_param.'&page_size=150' : '?page_size=150' ));
					$resultado = $this->chequear_respuesta($api_response, 'las actividades', $nombre_transient);
				} break;
				case 'todas_disciplinas': {
					$api_response = wp_remote_get(self::$URL_API_GOB_AB.'/disciplina-actividad/?audiencia_id='.self::$ID_AUDIENCIA_CULTURA);
					$resultado = $this->chequear_respuesta($api_response, 'las disciplinas', $nombre_transient);
				} break;
			}
		}
		return $resultado;
	}*/

	public function buscar_actividad()
	{
		$id = $_REQUEST['id'];
		
		check_ajax_referer('buscar_lugar_nonce', 'nonce');
		
		if(true && $id > 0) {
			$api_response = wp_remote_get(self::$URL_API_GOB_AB.'/lugar-actividad/'.$id);
			$api_data = json_decode(wp_remote_retrieve_body($api_response), true);
			
			$api_data = $this->mejorar_contenido_actividad($api_data);
			
			wp_send_json_success($api_data);
		} else {
			wp_send_json_error(array('error' => $custom_error));
		}
		
		die();
	}

	public function reiniciar_opciones()
	{
		$id = $_REQUEST['id'];
		
		check_ajax_referer('reiniciar_opciones_nonce', 'nonce');
		
		if (true) {
			delete_post_meta($id, self::$META_KEY_COLOR);
			delete_post_meta($id, self::$META_KEY_LOGO);
			delete_post_meta($id, self::$META_KEY_AUDIENCIA);
			echo admin_url('post.php?post=' . $id).'&action=edit';
		} else {
			echo 0;
		}
		
		wp_die();
	}

	
	
	

	private function mostrar_error($error)
	{
		if (WP_DEBUG === true) {
			return $error->get_error_message();
		}
	}

	private function quitar_palabras($texto, $palabras_devueltas)
	{
		$resultado = $texto;
		$texto = preg_replace('/(?<=\S,)(?=\S)/', ' ', $texto);
		$texto = str_replace("\n", " ", $texto);
		$arreglo = explode(" ", $texto);
		if (count($arreglo) <= $palabras_devueltas) {
			$resultado = $texto;
		} else {
			array_splice($arreglo, $palabras_devueltas);
			$resultado = implode(" ", $arreglo) . "...";
		}
		return $resultado;
	}

	private function formatear_fecha_tres_caracteres($timestamp)
	{
		$fecha = strftime("%b %d", strtotime($timestamp));
		$fecha = $this->traducir_meses($fecha); // Ene 1
		return $fecha;
	}

	private function formatear_fecha_inicio_fin($timestamp)
	{
		$fecha = strftime("%e %h, %H:%M hs.", strtotime($timestamp));
		$fecha = $this->traducir_meses($fecha);
		return $fecha;
	}

	private function formatear_fecha_listado($timestamp)
	{
		$fecha = strtotime($timestamp);
		return strftime("%e de %B, %Y %H:%M", $fecha); // 7 de Abril, 2017 14:22
	}

	private function traducir_meses($texto)
	{
		return str_ireplace(self::$MONTHS, self::$MESES, $texto);
	}
	
	
	private function es_pasado($timestamp)
	{
		$fecha = date('Y.m.d', strtotime($timestamp));
		$hoy = date('Y.m.d');
		return $fecha < $hoy;
	}

	private function que_dia_es($timestamp_inicio, $timestamp_fin)
	{
		$fecha_inicio = date('Y.m.d', strtotime($timestamp_inicio));
		$fecha_fin = date('Y.m.d', strtotime($timestamp_fin));
		$hoy = date('Y.m.d');
		$maniana = date('Y.m.d', strtotime('+1 day'));
		
		if ($fecha_inicio == $maniana) {
			return 'MA&Ntilde;ANA';
		} elseif ($fecha_inicio <= $hoy) {
			return 'HOY';
		} else {
			return false;
		}
	}

	private function cargar_url_asset($ruta_archivo)
	{
		return plugins_url($this->minified($ruta_archivo), __FILE__);
	}

	// Se usan archivos minificados en producción.
	private function minified($ruta_archivo)
	{
		if (WP_DEBUG === true) {
			return $ruta_archivo;
		} else {
			$extension = strrchr($ruta_archivo, '.');
			return substr_replace($ruta_archivo, '.min'.$extension, strrpos($ruta_archivo, $extension), strlen($extension));
		}
	}
	
	private function friendly_url($str)
	{	
		$str = iconv('UTF-8','ASCII//TRANSLIT',$str);
		return strtolower( preg_replace(
			array( '#[\\s-]+#', '#[^A-Za-z0-9\. -]+#' ),
			array( '-', '' ),
			urldecode($str)
		) );
	}
}
