<!-- Herramienta web para dividir archivos PDF por criterios 
 @author  4L3J4NDR0 4N4Y4 (energy1011[4t]gmail[d0t]com) 2014 -->
 
<!-- vista del modal para cargar archivos pdf -->
<html>
<meta charset="UTF-8">
<head>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
</head>
<body>

<script type='text/javascript'>
var url_procesa_archivo = '<?=$url_procesa_archivo?>';
var url_descarga_zip = '<?=$url_descarga_zip?>';
var url_PDFSplitter_manual = '<?=$ruta_pdfsplitter_manual?>';
var cargando_archivo = "<?=lang('cargando_archivo')?>";

	//funcion que captura el resultado del uploadfile
	function resultadoUpload(result)
	{
		$('#div_mensajes').html("<img src='<?php echo base_url('images/PDFSplitter/ajax-loader.gif'); ?>'> "+cargando_archivo);
		eval('var jsonObj = '+result+';');
		var resultado = jsonObj.resultado;
		var mensaje = '';

			// Falla al subir el archivo
			if (resultado == 1){
				 mensaje = jsonObj.mensaje;
				 $("#save_import_button").attr('disabled', true);
			}

			// Archivo subido correctamente
			if(resultado == 0) 
			{
				mensaje = jsonObj.mensaje;
				filename = jsonObj.filename;
				// Proceso el archivo pdf
				procesar_pdf(filename);
			}

			 // Agrego el mensaje al div
			 if (mensaje!='') { $("#div_mensajes").html(mensaje); };

	}

	// Funcion con Ajax para procesar un archivo pdf importado
	function procesar_pdf(filename)
	{
		var mode = $('#mode').val();
		$('#div_mensajes').html("<img src='<?php echo base_url('images/PDFSplitter/ajax-loader.gif'); ?>'>"+cargando_archivo);
		$.ajax({  
			type: 'POST',
			url: url_procesa_archivo,
			data: {filename: filename, mode: mode}
		}).done(function(json){
			// Pruebo que la respuesta sea json y no un exception string
			try{
				    var json = JSON.parse(json);
					var resultado = json.resultado;
					var mensaje = json.mensaje;
					var msjs_proceso = json.msjs_proceso;

					// Error al procesar el archivo
					if (resultado == 1){
						 $("#save_import_button").attr('disabled', true);
						 if (mensaje!='') { $("#div_mensajes").html(mensaje); };
						 $('#tabla_archivo_pdf').html(html); 
					}

					// Archivo procesado correctamente
					if(resultado == 0) 
					{
						if (mensaje!='') { $("#div_mensajes").html(mensaje); };
						if (msjs_proceso!='') { $("#div_mensajes").html(msjs_proceso); };
						download_file();
					}
			}
			catch(e)
			{
				$('#div_mensajes').html('El archivo tiene una compresion no permitida, lea el manual para cambiar el tipo de compresion del archivo '+ '<a id="downloadLink" title="Clic para descargar manual" href="#" onclick="download_manual();return false;">VER MANUAL</a>');
			}

		}).fail(function(json){
     		$('#div_mensajes').html('Falla Ajax.'); 
		});
		return;	
	}

	function download_file()
	{
		window.open(url_descarga_zip, 'new');
	}

	function download_manual()
	{
		window.open(url_PDFSplitter_manual, 'new');
	}
	
</script>
<div>

<h3><img src='<?php echo base_url('images/PDFSplitter/icon-mime-PDF.png'); ?>'> <?=lang('nombre_helper')?></h3>
	<!-- Carga el archivo de pdf -->
	<h4><?=lang('seleccione_archivo')?></h4>
 	 <?php if (isset($error)){
	 	echo $error;
	 }?>

 <!-- File chooser para subir el archivo de pdf -->
	<?php echo form_open_multipart($url_sube_archivo, array('id'=>'form_upload_file', 'target'=>'myiframe'));?>
	<input type="file" name="userfile" size="20" /><br>
	<input type="submit" value="<?=lang('cargar_archivo')?>" />
	</form>

<label ><b><?=lang('elija_modo')?>:</b></label>	
<select id='mode'>
<!-- (Energy1011) TODO: Agregar más modos -->
	<option value="1">CLC</option>
	<option value="2">Divide por hoja</option>
</select>
<br>

	<div style="margin-top: 2em;  background-color:#CC0000; height:1.5em; width:50%; color:white;"><b> - PDFSplitter - Proceso del documento</b></div>
 	<div id='div_mensajes' style="height:300px; width:50%; overflow: scroll; border: 2px solid #a1a1a1; box-shadow: 10px 10px 5px #888888;"></div>
	</div>
</div>
	<!-- iframe para evitar cargar y pasar a otra pagina al hacer submit multipat file-->
	<iframe id="myiframe" name="myiframe" hidden></iframe>
</body>
</html>