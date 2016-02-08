<?php
/**
 * Clase helper: Clase controlador para manejar el PDFSplitter
 * @author  4L3J4NDR0 4N4Y4 (energy1011[4t]gmail[d0t]com) 2014
 */

class PDFSplitterHelper extends CI_Controller {

	// Constructor de la clase
	function __construct()
	{
		parent::__construct();
		// Cargo los helpers para la clase
		$this->load->helper(array('form', 'url', 'language', 'PDFSplitter'));
		//Cargo el idioma para el helper
		$this->lang->load('PDFSplitter');
		//Cargo sesiones para para guardar el nombre del ultimo archivo subido por el usuario
		$this->load->library('session');
	}

	// Index principal
	public function index()
	{
		$data['url_sube_archivo'] = site_url('PDFSplitterHelper/do_upload');
		$data['url_procesa_archivo'] = site_url('PDFSplitterHelper/process_pdf_file');
		$data['url_descarga_zip'] = site_url('PDFSplitterHelper/download_zip');
		$data['ruta_pdfsplitter_manual'] = $this->config->item('ruta_pdfsplitter_manual');
		// Cargo la vista
		$this->load->view('PDFSplitterHelper/carga_pdf',$data);
	}

	/**
	 * Sube archivos pdf al servidor
	 * LLamada desde Ajax
	 * @author  4L3J4NDR0 4N4Y4 (energy1011[4t]gmail[d0t]com) 2014
	 * @uses    do_upload (libreria upload)
	 * @return  JSON resultado (Resultado de subir el archivo) , mensaje (Mensajes de error o de ok), filename (Nombre del archivo subido)
	 */
	function do_upload()
	{
		$config['upload_path'] = $this->config->item('ruta_upload_files_pdf');
		$config['allowed_types'] = 'pdf';
		$config['max_size']	= '20000'; // 20 megas
		$config['max_width']  = '1024';
		$config['max_height']  = '768';
		$this->load->library('upload', $config);
		$resultado = array('resultado' => 0 );
		if ( ! $this->upload->do_upload())
		{
  			echo '<script type="text/javascript">
					var texto = "'.lang('error_subir_archivo').'";
					alert(texto);
				  </script>';
			$data = array('error' => $this->upload->display_errors(), 'url_procesa_archivo' => site_url('PDFSplitterHelper/process_pdf_file'), 'url_sube_archivo' => site_url('PDFSplitterHelper/do_upload'));
			// Borramos posible archivo subido a la sesion anteriormente
			$this->session->unset_userdata('PDFSplitterHelper_last_file_upload');
			$resultado['mensaje'] = $this->upload->display_errors();
			$resultado['resultado'] = 1;
		}
		else
		{
			$data = array('upload_data' => $this->upload->data(), 'url_procesa_archivo' => site_url('PDFSplitterHelper/process_pdf_file'), 'url_sube_archivo' => site_url('PDFSplitterHelper/do_upload'));
			$resultado['resultado'] = 0;
			$resultado['mensaje'] = lang('archivo_cargado_ok');
			$resultado['filename'] = $data['upload_data']['file_name'];
			// Guardamos en la sesion el ultimo archivo subido
			$this->session->set_userdata('PDFSplitterHelper_last_file_upload', $data['upload_data']['file_name']);
		}

		//Como es el iframe el que se da la respuesta, se utiliza un parent.function();
		echo "<script type='text/javascript'>parent.resultadoUpload('".json_encode($resultado)."');</script>";
	}

	/**
	 * Procesa un archivo de pdf importado
	 * @author  4L3J4NDR0 4N4Y4 (energy1011[4t]gmail[d0t]com) 2014
	 * @param   POST String $inputFileName Nombre del archivo impotado a procesar
	 * @return  JSON
	 */
	function process_pdf_file()
	{
		$tiempoInicio = time();
		$inputFileName = $this->input->post('filename');
		$mode = $this->input->post('mode');
		// Inicializo la variable resultado para la respuesta ajax
		$resultado = array('resultado' => 0 );
		// Compruebo que los post no vengan vacios
		if ($inputFileName == ''){
				PDFSplitter_log_it($this->config->item('ruta_pdfsplitter_log'), "Error: No filename proporcionado por post");
				//No se ha proporcionado filename
				$resultado['mensaje'] = lang('verifique_los_post');
				$resultado['resultado'] = 1;
				echo json_encode($resultado);
		}

	// Obtengo las rutas necesarias del config
	$pathResultSplits = $this->config->item('ruta_splited_files');
	$pathSource = $this->config->item('ruta_upload_files_pdf');

	switch($mode){
		case 1:
			// Proceso el archivo, pasando los parametros que sirven de criterio para el split
			$resultado['msjs_proceso'] = PDFSplitter_split($inputFileName, $pathSource, $pathResultSplits, 'FOLIO CLC:', '102.04 0', 'L', 'Letter', '/\((.*?)\)/', 27, 'clc');
			break;
		case 2:
			$resultado['mensaje_proceso'] = PDFSplitter_split_by_page($inputFileName, $pathSource, $pathResultSplits);
			break;
	}
	//Obtengo el size del archivo antes de ser spliteado
	$thisFileSize = (int)(filesize($pathSource.$inputFileName) / 1000);

	// Borro el archivo del cual se hizo la division, ya no se necesita almacenar
	unlink($pathSource.$inputFileName);
	echo json_encode($resultado);

	$tiempoFinal = time();
	$tiempoTotal = $tiempoFinal - $tiempoInicio;
	PDFSplitter_log_it($this->config->item('ruta_pdfsplitter_log'), "Se ha procesado el archivo: $inputFileName (".$thisFileSize." KB) en ".$tiempoTotal." segundos");

	}

	/**
	* Regresa el zipfile descargable
	* @author  4L3J4NDR0 4N4Y4 (energy1011[4t]gmail[d0t]com) 2014
	* @uses    PDFSplitter_get_zipfile()
	* @return  boolean
	*/
	function download_zip()
	{

		$result = PDFSplitter_get_zipfile($this->config->item('ruta_splited_files'), $this->config->item('ruta_zipfile'), 'Archivos.zip');
		if($result == false){
			echo "Error al generar el archivo descargable";
			PDFSplitter_log_it($this->config->item('ruta_pdfsplitter_log'), "Error: al entregar el archivo descargable.");

		}else{
		return  $result;
		}
	}

	function download_manual($pathManual,$filenameManual)
	{
			header('Content-Type: application/zip');
			header('Content-Disposition: attachment; filename="'.$filenameManual.'";');
			header("Accept-Ranges: bytes");
			readfile($pathManual);
	}
}// End class
?>