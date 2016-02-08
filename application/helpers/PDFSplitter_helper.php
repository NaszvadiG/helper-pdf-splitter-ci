<?php
/**
 * PDFSplitter: Divisor de archivos PDF por criterios y reglas
 * @author  4L3J4NDR0 4N4Y4 (energy1011[4t]gmail[d0t]com) 2014
 */

require_once('fpdf/fpdf.php');
require_once('fpdi/fpdi.php');
require_once('PDFMerger.php');

define('BR','</br>');
define('ICON_IMG',"<img src='images/PDFSplitter/icon-mime-PDF.png'?>");

/**
 * Borra todos los archivos PDF del directorio ($path) que contienen el session_id en el nombre
 * @author  4L3J4NDR0 4N4Y4 (energy1011[4t]gmail[d0t]com) 2014
 * @uses    glob()
 * @uses    unlink()
 * @param   String $path directorio donde se encuentran los posibles archivos
 * @param   String $fileFormat formato de archivos a buscar para eliminar .pdf o .zip
 * @return  void
 */
function PDFSplitter_delete_all_session_files($path, $fileFormat)
{
	$my_session_id = session_id();
	// Borro todos los archivos que puedan existir con el id session
	foreach (glob($path.$my_session_id."*".$fileFormat) as $nombre_archivo) {
		unlink($nombre_archivo);
	}
}

/**
 * Divide el pdf por hojas
 * @author  4L3J4NDR0 4N4Y4 (energy1011[4t]gmail[d0t]com) 2015
 * @param   String $filename nombre del archivo PDF a procesar (sin ruta)
 * @param   String $pathSource ruta del archivo PDF a dividir
 * @param   String $pathSplits ruta para guardar los PDF divididos resultantes
 * @param   String $orientationPage orientacion de la pagina 'P' = normal vertical 'L' = landscape
 * @param   String $sheetType tipo o tamaño de hoja 'Letter' , 'A4', etc
 * @return  tipo retorna
 */
function PDFSplitter_split_by_page($filename='', $pathSource = '',$pathSplits='',$orientationPage= 'P', $sheetType = 'Letter'){
	@session_start();
	$responseTXT = ''; // mensajes de lo sucedido durante el proceso
	$filename = trim($filename);
	$pathSplits = trim($pathSplits);

	if (is_null($filename)) {
		$responseTXT .= BR."Es necesario el nombre del archivo a leer.";
		return $responseTXT;
	}
	if (is_null($pathSplits)) {
		$responseTXT .= BR."No se ha indicado ruta donde se guardaran los archivos resultantes del split.";
		return $responseTXT;
	}

	// Creo objeto FPDI y obtengo el numero de paginas
	$pdf = new FPDI();
	// Defino el archivo a procesar en el objeto PDF
	$pagecount = $pdf->setSourceFile($pathSource.$filename);

// Inicializo la variable que guarda el nombre del ultimo archivo (pagina dividida)
$last_filename = '';

	// Obtengo el session id
	$my_session_id = session_id();

	if ($pagecount > 0 ) {
		// Itero sobre las paginas del documento PDF
		for ($i = 1; $i <= $pagecount; $i++) {
			$new_pdf = new FPDI();
			$new_pdf->setSourceFile($pathSource.$filename);
			$importedPage = $new_pdf->importPage($i);
			$new_pdf->AddPage($orientationPage, $sheetType);
			$new_pdf->useTemplate($importedPage);

			try {
				$thisTagGotContent = '';
				// Agrego id de session al nombre del archivo para evitar colisiones de archivos con otros usuarios
				$thisFilename = $my_session_id."_".$filename;
				// Verifico nuevamente que no exista el archivo
				if (file_exists($pathSplits.$thisFilename)) {
					$responseTXT .= BR."Existe un archivo con el mismo nombre, vuelva a intentarlo.";
					return $responseTXT;
				}

				// Agrego un consecutivo al nombre del archivo (el numero de hoja)
				$thisFilename = str_replace('.pdf','-'.$i.'.pdf', $thisFilename);

				$new_pdf->Output($pathSplits.$thisFilename, "F");
				$responseTXT .= BR.BR.ICON_IMG."Hoja ".$i." dividida ";

					// Guardo el nombre del archivo tratado en la iteracion actual para usarlo en la siguiente iteracion
					$last_filename = $pathSplits.$thisFilename;
			} catch (Exception $e) {
				PDFSplitter_log_it($this->config->item('ruta_pdfsplitter_log'), "Archivo con compresion no permitida.");
				// Tenemos una exception
				$responseTXT = $e->getMessage()."\n";
				$new_pdf->cleanUp();
				// Borro el archivo .pdf fuente que se iba a dividir
				unlink($pathSource.$filename);
				return $responseTXT;
			}
			$new_pdf->cleanUp();
			// libero memoria del objeto hoja pdf de esta iteracion
			unset($new_pdf);
		} // End for iteracion por hojas del pdf
	}

}

/**
 * Funcion para dividir archivos PDF a partir de una tag encontrada en cada hoja
 * y nombramiento de archivos resultantes a partir de otra tag de la hoja
 * @author  4L3J4NDR0 4N4Y4 (energy1011[4t]gmail[d0t]com) 2014
 * @uses    FPDF Class (IMPORTANTE: Modificada especialmente para este uso )
 * @uses    FPDI Class
 * @uses    PDFMerger Class ( Modificada especialmente para este uso )
 * @param   String $filename nombre del archivo PDF a procesar (sin ruta)
 * @param   String $pathSource ruta del archivo PDF a dividir
 * @param   String $pathSplits ruta para guardar los PDF divididos resultantes
 * @param   String $tagRuleToSplit palabra (tag) del PDF que funciona como disparador para la division de hojas
 * @param   String $posTagToNameFile posicion de la tag con coordenadas formato PDF que necesitamos extraer para nombrar el archivo con el contenido de esta etiqueta
 * @param   String $orientationPage orientacion de la pagina 'P' = normal vertical 'L' = landscape
 * @param   String $sheetType tipo o tamaño de hoja 'Letter' , 'A4', etc
 * @param   String $regex para obtener la TagToNameFile dentro del flujo string del PDF
 * @param   String $stringSizeToRegex tamaño de la cadena a tomar despues de encontrar las coordenadas de la TagToNameFile se posteriormente se le aplica el regex
 * @param   String $splitName nombre general para todos los splits que estaran dentro del zip, si no se pasa un splitName a esta funcion, el nombre para cada uno de los splits es igual al del archivo importado
 * @return  String $responseTXT respuesta del proceso en texto
 */
function PDFSplitter_split($filename='', $pathSource = '',$pathSplits='', $tagRuleToSplit='', $posTagToNameFile= '', $orientationPage= 'P', $sheetType = 'Letter', $regex = '/\((.*?)\)/', $stringSizeToRegex = 27, $splitName=NULL)
{
	@session_start();
	$responseTXT = ''; // mensajes de lo sucedido durante el proceso
	$filename = trim($filename);
	$pathSplits = trim($pathSplits);
	$posTagToNameFile = trim($posTagToNameFile);
	$orientationPage = trim(strtoupper($orientationPage));
	$sheetType = trim(ucfirst($sheetType));
	$stringSizeToRegex = (int)$stringSizeToRegex;

	if (is_null($filename)) {
		$responseTXT .= BR."Es necesario el nombre del archivo a leer.";
		return $responseTXT;
	}
	if (is_null($pathSplits)) {
		$responseTXT .= BR."No se ha indicado ruta donde se guardaran los archivos resultantes del split.";
		return $responseTXT;
	}
	if (is_null($tagRuleToSplit)) {
		//TODO: sin tagrule
		$responseTXT .= BR."No se ha definido un texto o tag de criterio para dividir las hojas, por default el criterio de division sera por hojas.";
	}
	if(is_null($posTagToNameFile)){
		$responseTXT .= BR."No se ha definido ninguna dato a extraer de las hojas, para usarse en el nombramiento de archivos resultantes, por default sera el num de hoja.";
	}

	// Creo objeto FPDI y obtengo el numero de paginas
	$pdf = new FPDI();
	// Defino el archivo a procesar en el objeto PDF
	$pagecount = $pdf->setSourceFile($pathSource.$filename);
	// Inicializo la variable que guarda el nombre del ultimo archivo (pagina dividida)
	$last_filename = '';

	// Obtengo el session id
	$my_session_id = session_id();

	// Borro posibles splits anteriores con el mismo session_id
	PDFSplitter_delete_all_session_files($pathSplits, '.pdf');

	if ($pagecount > 0 ) {
		// Itero sobre las paginas del documento PDF
		for ($i = 1; $i <= $pagecount; $i++) {
			$new_pdf = new FPDI();
			$new_pdf->setSourceFile($pathSource.$filename);
			$importedPage = $new_pdf->importPage($i);
			$new_pdf->AddPage($orientationPage, $sheetType);
			$new_pdf->useTemplate($importedPage);

			try {
				$thisTagGotContent = '';
				// Agrego id de session al nombre del archivo para evitar colisiones de archivos con otros usuarios
				$thisFilename = $my_session_id."_".$filename;
				// Verifico nuevamente que no exista el archivo
				if (file_exists($pathSplits.$thisFilename)) {
					$responseTXT .= BR."Existe un archivo con el mismo nombre, vuelva a intentarlo.";
					return $responseTXT;
				}

				// Agrego un consecutivo al nombre del archivo (el numero de hoja)
				$thisFilename = str_replace('.pdf','-'.$i.'.pdf', $thisFilename);

				//Verifica si cada split lleva un nombre
				if($splitName != NULL){
					//  cada split se le agrega el nombre pasado por parametro
					$thisFilename = str_replace(str_replace('.pdf','',$filename),$splitName, $thisFilename);
				}

				$new_pdf->Output($pathSplits.$thisFilename, "F");
				$responseTXT .= BR.BR.ICON_IMG."Hoja ".$i." dividida ";

				// Obtengo la posicion de la etiqueta que voy a extraer para utilizara como parte de nombre del archivo
				$thisPosTagToGet = @strpos($new_pdf->mybuffer, $posTagToNameFile);
				// Obtengo la posicion de la etiqueta que usare como disparador del split
				$thisPosTriggerTab = @strpos($new_pdf->mybuffer, $tagRuleToSplit);

			// Si existe la etiqueta a extraer en la hoja actual y existe la etiqueta trigger del esplit (Es una hoja que inicia el archivo y necesitamos extraer la etiqueta para nombrarlo)
				if ($thisPosTagToGet != false && $thisPosTriggerTab != false) {
					// Obtengo la string con las coordenadas y doy 27 caracteres de tolerancia en la cadena a partir de la posicion de las coordenadas en el string
					$myStringTag = substr($new_pdf->mybuffer, $thisPosTagToGet, $stringSizeToRegex);

					// Libero memoria
					unset($new_pdf->mybuffer);

					// Obtengo el valor de la etiqueta que esta dentro del parentesis
					preg_match($regex, $myStringTag, $result);

					//Libero memoria
					unset($myStringTag);

					$thisTagGotContent = $result[1];
					// Defino nuevo nombre al archivo a partir del tag obtenido
					$newNameWithTag = str_replace('-'.$i.'.pdf', '_'.$thisTagGotContent.'.pdf', $thisFilename);
					// $responseTXT .= BR."--Nombre actual:".$thisFilename;
					// $responseTXT .= BR."--Nuevo nombre:".$newNameWithTag;

					// Renombro el archivo con el nuevo tag incluido
					@rename($pathSplits.$thisFilename,$pathSplits.$newNameWithTag);
					// $responseTXT .= BR."--Archivo renombrado a:".$pathSplits.$newNameWithTag;
					$thisFilename = $newNameWithTag;
				}

			//Necesito un merge con la hoja anterior ?
				// No tenemos la trigger tab para el split entonces tenemos una hoja que es consecutiva y necesitamos un merge con el archivo anterior
				if ($thisPosTriggerTab === false && strlen($last_filename)>0 && $thisPosTagToGet === false) {
					// Obtengo la cadena dond e se encuentran los datos de X etiqueta (Folio dentro del documento)
				    $responseTXT .= BR."---->".ICON_IMG."Hoja:".$i." Esta es una hoja consecutiva, realizo merge con la hoja anterior.";

				    $config = array('myOrientation' => $orientationPage, 'mySheetType' => $sheetType);
				    $pdfMerge = new PDFMerger($config);
					$pdfMerge->addPDF($last_filename, 'all')->addPDF($pathSplits.$thisFilename, 'all')->merge('file',$last_filename);

					// borro el archivo que ya hice merge con la hoja anterior
					if (file_exists($pathSplits.$thisFilename)) {
						// $responseTXT .= BR."--Borre el archivo:".$thisFilename;
						unlink($pathSplits.$thisFilename);
						// Libero memoria del merge merge
						unset($pdfMerge);
						continue;
					}

				}
					// Guardo el nombre del archivo tratado en la iteracion actual para usarlo en la siguiente iteracion
					$last_filename = $pathSplits.$thisFilename;
			} catch (Exception $e) {
				PDFSplitter_log_it($this->config->item('ruta_pdfsplitter_log'), "Archivo con compresion no permitida.");
				// Tenemos una exception
				$responseTXT = $e->getMessage()."\n";
				$new_pdf->cleanUp();
				// Borro el archivo .pdf fuente que se iba a dividir
				unlink($pathSource.$filename);
				return $responseTXT;
			}
			$new_pdf->cleanUp();
			// libero memoria del objeto hoja pdf de esta iteracion
			unset($new_pdf);
		} // End for iteracion por hojas del pdf

		return $responseTXT .= BR."Proceso completado.";
	}// end if pages < 0
}


/**
 * Muestra el flujo de strings contenidos en un pdf por cada hoja, con esta funcion podemos encontrar las coordenadas de las etiquetas y armar un regex adecuado
 * @author  4L3J4NDR0 4N4Y4 (energy1011[4t]gmail[d0t]com) 2014
 * @uses 	FPDI Class
 * @param   String $filename nombre del archivo PDF
 * @return  String Echo or 1 = error
 */
function PDFSplitter_show_internal_strings($filename)
{
	// TODO: Crear un calibrador GUI para obtener las coordenadas mediante marcacion visual
	$responseTXT = '';
	if (is_null($filename)) {
	echo BR."Es necesario el nombre del archivo a leer";
	return $responseTXT;
	}

	// initiate FPDI obtengo el numero de paginas
	$pdf = new FPDI();
	// Defino el archivo a procesar en el objeto PDF
	$pagecount = $pdf->setSourceFile($filename);
	// Inicializo la variable que guarda el nombre del ultimo archivo (pagina dividida)
	$last_filename = '';

	if ($pagecount > 0 ) {
		// Itero sobre las paginas del documento PDF
		for ($i = 1; $i <= $pagecount; $i++) {
			echo BR.BR."Hoja: $i".BR;
				$new_pdf = new FPDI();
				$new_pdf->setSourceFile($filename);
				$importedPage = $new_pdf->importPage($i);
				$new_pdf->AddPage($orientationPage, $sheetType);
				$new_pdf->useTemplate($importedPage);
				echo $new_pdf->mybuffer;
				// Libero memoria de buffer
				unset($new_pdf->mybuffer);
		}
	}

}

/**
 * Genera el archivo zip que contiene los splits
 * @author  4L3J4NDR0 4N4Y4 (energy1011[4t]gmail[d0t]com) 2014
 * @uses    session_start()
 * @uses    session_id()
 * @uses    basename()
 * @uses    Class ZipArchive()
 * @uses    PDFSplitter_delete_all_session_files()
 * @uses    header()
 * @uses    readfile()
 * @uses    unlink()
 * @param   $pathSplits ruta donde se encuentran los splits
 * @param   $pathDest ruta donde se guardara el archivo zip a descargar por un instante
 * @param   $filename nombre del archivo zip resultante
 * @return  boolean
 */
function PDFSplitter_get_zipfile($pathSplits, $pathDest, $filename)
{
	@session_start();
	// Bandera para saber si se comprimio cuando menos un archivo
		$flagExistFile = false;
	// Obtengo el session_id para buscar los splits que coinciden con el session_id actual
		$my_session_id = session_id();
		$filename = basename($filename);
		// Genero un nuevo nombre y ruta completa para el zip que contiene ademas la session_id para evitar colisiones nombres de archivos
		$newZipFileName = $pathDest.$my_session_id.$my_session_id.$filename;

		// Borro archivos zip anteriores
		PDFSplitter_delete_all_session_files($pathDest, '.zip');

		// Zipeo los archivos
		$zip = new ZipArchive();
		if($zip->open($newZipFileName, ZIPARCHIVE::CREATE) !== true) {
			// Error al crear el archivo zip
			return false;
		}

		// Itero sobre todos los archivos generados con el mismo session_id
		foreach (glob($pathSplits.$my_session_id."*.pdf") as $nombre_archivo) {
			// Agrego el archivo actual al zip, con el nombre sin session_id y sin path
			$zip->addFile($nombre_archivo,str_replace($pathSplits.$my_session_id.'_', '',$nombre_archivo));
			$flagExistFile = true; // Tengo cuando menos un archivo
		}
		// Compruebo que se tenemos almenos un archivo en el zip
		if ($flagExistFile == false) {
			// No se zipio ningun archivo, cierro el archivo
			$zip->close();
			// Borro el archivo que no contiene nada
			@unlink($newZipFileName);
			return false;
		}else{
			// Cierro la escritura del objeto y el archivo para poder leer sus datos
			$zip->close();

		// Defino los headers para regresar el archivo zip al browser
			if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
			    // cache settings for IE6 on HTTPS
			    header('Cache-Control: max-age=120');
			    header('Pragma: public');
			} else {
			    header('Cache-Control: private, max-age=120, must-revalidate');
			    header("Pragma: no-cache");
			}

			header('Content-Type: application/zip');
			header('Content-Disposition: attachment; filename="'.$filename.'";');
			header("Accept-Ranges: bytes");

			//Regreso el archivo al navegador
			readfile($newZipFileName);

			//Borro el archivo zip generado en directorio
			unlink($newZipFileName);

			// Borro archivos splits que acabo de zipear, ya no se necesitan
			PDFSplitter_delete_all_session_files($pathSplits, '.pdf');

		}
		return true;
}

/**
 * Escribe la bitacora (log) de PDFSplitter
 * @author  4L3J4NDR0 4N4Y4 (energy1011[4t]gmail[d0t]com) 2014
 * @uses    file_exists()
 * @uses    date()
 * @uses    fopen()
 * @uses    fwrite()
 * @uses    fclose()
 * @param   $pathLogFile ruta del archivo log
 * @param   $textToLog texto a guardar en el log (descripcion de la actividad)
 * @return  boolean
 */
function PDFSplitter_log_it($pathLogFile, $textToLog){

	// Obtengo la fecha para agregarla a la linea del log
	$date = date('Y-m-d H:i:s');

	// Compruebo si existe al archivo de logs
	if(!file_exists($pathLogFile)){
	// Es importante crear el archivo log si no existe, ya que codeigniter en su gitignore no sigue a los archivos existentes de la carpeta log
		$file=fopen($pathLogFile,"x");
		$contenido = "*LOG INICIADO: ".$date.PHP_EOL." PDFSplitter - ".PHP_EOL.date('Y-m-d').PHP_EOL.PHP_EOL;
		fwrite($file,$contenido);
		fclose($file) ;
	}

	$user = "Public";//(Energy1011) TODO: Agregar un nombre de usuario

	$file = fopen($pathLogFile, "a");
	if ($file == null) {
		echo "Error al abrir el archivo log con fopen.";
		return false;
	}
	//(Energy1011) TODO: Verificar el size del log y reducirlo
	$textToLog = $user." ".$_SERVER['REMOTE_ADDR']." ".$date." ".$textToLog;
	// Escribo en el archivo
	fwrite($file, $textToLog.PHP_EOL);
	fclose($file);
	return true;
}

?>