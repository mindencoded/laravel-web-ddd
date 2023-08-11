<?php
$return = array();
$return["memory_init"]=memory_get_usage();
$return["time_init"] = microtime(true);
include("global_config.php");
include("db_connect.php");
include("sys_login.php");
require_once '/var/www/html/sys/helpers.php';

function log_incidencias($msg , $valid )
{
	if( is_array($msg) )
	{
		$msg = json_encode($msg, JSON_PRETTY_PRINT |JSON_UNESCAPED_UNICODE);
	}
	$msg = date("Y-m-d H:i:s ") . $msg;
	if (in_array(1,$valid)) {
		file_put_contents('../logs/incidencias.log', $msg.PHP_EOL , FILE_APPEND | LOCK_EX);
	} 
	if (in_array(2,$valid)){
		file_put_contents('../logs/incidencias_bad.log', $msg.PHP_EOL , FILE_APPEND | LOCK_EX);
	}
 	
}

function resizeImage($resourceType, $image_width, $image_height)
{
    $imagelayer = [];
    if ($image_width < 1920 && $image_height < 1080) {

        $imagelayer[0] = imagecreatetruecolor($image_width, $image_height);
        imagecopyresampled($imagelayer[0], $resourceType, 0, 0, 0, 0, $image_width, $image_height, $image_width, $image_height);

    } else {
        $ratio = $image_width / $image_height;
        $escalaW = 1920 / $image_width;
        $escalaH = 1080 / $image_height;

        if ($ratio > 1) {
            $resizewidth = $image_width * $escalaW;
            $resizeheight = $image_height * $escalaW;

        } else {
            $resizeheight = $image_height * $escalaH;
            $resizewidth = $image_width * $escalaH;
        }
        $imagelayer[0] = imagecreatetruecolor($resizewidth, $resizeheight);
        imagecopyresampled($imagelayer[0], $resourceType, 0, 0, 0, 0, $resizewidth, $resizeheight, $image_width, $image_height);

    }
    return $imagelayer;
}

if(isset($_POST["sec_incidencias_save"])){
	$data=$_POST["sec_incidencias_save"];

	if($data["incidencia_txt"]==""){
			$return["error"]="incidencia_txt";
			$return["error_msg"]="Debe ingresar Incidencia";
			$return["error_focus"]="incidencia_txt";	

	}
	elseif($data["local_id"]=="" || $data["local_id"]==0 ){
			$return["error"]="local_id";
			$return["error_msg"]="Debe Seleccionar Tienda";
			$return["error_focus"]="local_id";
	}
	elseif(!isset($data["selectProducto"]) || $data["selectProducto"] == ""){
			$return["error"]="selectProducto";
			$return["error_msg"]="Debe Seleccionar Producto";
			$return["error_focus"]="selectProducto";
	}
	elseif(!isset($data["selectTipo"]) || $data["selectTipo"] == ""){
			$return["error"]="selectTipo";
			$return["error_msg"]="Debe Seleccionar Tipo";
			$return["error_focus"]="selectTipo";
	}
	elseif( !isset($data["telefono2"]) || $data["telefono2"] == ""){
			$return["error"]="telefono2";
			$return["error_msg"]="Debe ingresar Teléfono";
			$return["error_focus"]="telefono2";
	}
	elseif( !isset($data["reimpresion"]) || $data["reimpresion"] == ""){
			$return["error"] = "reimpresion";
			$return["error_msg"] = "Debe seleccionar Reimpresión";
	}
    else{
		$caracteres=strlen($data["incidencia_txt"]);
		if($caracteres>160){

			$return["error"]="incidencia_txt";
			$return["error_msg"]="Máximo 160 caracteres,  ingresó ".$caracteres;
			$return["error_focus"]="incidencia_txt";	
		}
		else{
			$tienda_id = $data["local_id"];
			$command = " SELECT id,created_at,update_user_at
				FROM tbl_soporte_incidencias
				WHERE estado IN (0,2) AND local_id = $tienda_id
				ORDER BY id DESC
			";
			$list_query = $mysqli->query($command);
			$lista_pendiente = array();
			while ($li = $list_query->fetch_assoc()) {
				$lista_pendiente[] = $li;
			}
			$incidencias = count($lista_pendiente);
			if( $incidencias > 0 ){
				//$text = $incidencias > 1 ? " incidencias" : " incidencia";
				//$return["error_msg"] = "Actualmente tienes " . count($lista_pendiente) . $text ."  en curso";
				$return["error_msg"] = "Ha alcanzado el límite de incidencias abiertas";
				$return["error"] = "local_id";
				$return["swal_type"] = "error";
				$return["swal_timeout"] = 5000;
			}else{
				$incidencia_txt = $mysqli->real_escape_string($data["incidencia_txt"]);
				$producto = $mysqli->real_escape_string($data["selectProducto"]);
				$tipo = $mysqli->real_escape_string($data["selectTipo"]);
				$telefono2 = $mysqli->real_escape_string($data["telefono2"]);
				$teamviewer_id = $data["teamviewer_id"] != "" ? "'".$mysqli->real_escape_string($data["teamviewer_id"])."'" : 'null';
				$teamviewer_password = $data["teamviewer_password"] != "" ? "'".$mysqli->real_escape_string($data["teamviewer_password"])."'" : 'null';
				$reimpresion = $data["reimpresion"];
				$insert_command = "
				INSERT INTO tbl_soporte_incidencias 
				(created_at
				,user_id
				,local_id
				,incidencia_txt
				,estado
				,producto
				,teamviewer_id
				,teamviewer_password
				,reimpresion
				,telefono2
				,tipo)
				VALUES(
				now()
				,".$login["id"]."
				,".$data["local_id"]."
				,'".$incidencia_txt."'
				,0
				,'$producto'
				,$teamviewer_id
				,$teamviewer_password
				,$reimpresion
				,".$telefono2."
				,'$tipo')
				";
				$mysqli->query($insert_command);
				if($mysqli->error){
					print_r($mysqli->error);
					echo "\n";
					echo $insert_command;
					exit();
				}
				$return["id"] = $mysqli->insert_id;
				$return["curr_login"]=$login;
				$return["mensaje"] = "Incidencia Enviada";
			}
		}
	}
}

if(isset($_POST["set_incidencias_get"])){
	$incidencia_id=$_POST["set_incidencias_get"];
	$user_id=$login["id"];
	$return["login_id"] = $user_id;

	if( isset($_POST["asignar"]) )
	{
		$command = "SELECT count(*) AS incidencias_asignadas 
					FROM tbl_soporte_incidencias
					WHERE agente_1_id = " . $login["id"] . "
					AND estado in (2)";
		$res_query = $mysqli->query($command)->fetch_assoc();
		if( $res_query["incidencias_asignadas"] >= 3 )
		{
			$return["error"] = "Tiene 3 incidencias asignadas";
			$return["swal_type"] = "error";
			$return["swal_timeout"] = 3000;
			echo json_encode($return);
			die();
		}
	}

	$command ="SELECT 
	inci.id 
	,loc.nombre as tienda
	,inci.created_at
	,inci.user_id
	,inci.local_id	
	,inci.incidencia_txt
	,inci.estado
	,inci.updated_at
	,inci.update_user_at
	,inci.update_user_id
    ,inci.agente_1_id
    ,inci.agente_2_id
	,inci.solucion_txt
	,usu.usuario as usuario
	,usu_agente.usuario as usuario_agente
	,CONCAT(usu_age_pers.nombre,' ',usu_age_pers.apellido_paterno) as nombre_agente
	,inci.producto
	,inci.tipo
	FROM tbl_soporte_incidencias inci 
	left join tbl_locales loc on  inci.local_id=loc.id
	left join tbl_usuarios usu on usu.id= inci.user_id
	left join tbl_usuarios usu_agente on usu_agente.id= inci.agente_1_id
	left join tbl_personal_apt usu_age_pers on usu_age_pers.id= usu_agente.personal_id
	where inci.id=".$incidencia_id;
	$list_query=$mysqli->query($command);
	
	$list=array();
	while ($li=$list_query->fetch_assoc()) {
		$list[]=$li;
	}
	if($mysqli->error){
		print_r($mysqli->error);
	}
	$objeto=$list[0];
	$estado=$objeto["estado"];
	$agente_id=$objeto["agente_1_id"];

	$incidencia_ya_asignada = false;
	$incidencia_ya_atendida = false;

	// 0 => Nuevo, 1 => Atendido, 2 => Asignado
	if($estado == 0 || $estado == 2){
        if($estado == 0){
            $udpate_command = "UPDATE tbl_soporte_incidencias SET 
            estado = 2
            ,update_user_at = now()
            ,update_user_id = ".$login["id"]."
            ,agente_1_id = ".$login["id"]." 
                where id= ".$incidencia_id;
            $mysqli->query($udpate_command);
            $return["mensaje"]="Incidencia ".$incidencia_id." asignada";
        }
        if($estado == 2){
            /*$udpate_command = "UPDATE tbl_soporte_incidencias SET
            estado = 2
            ,update_user_id = ".$login["id"]." 
                where id= ".$incidencia_id;*/
            $return["mensaje"] = "El caso ya ha sido asignado.";
            $return["status"] = "warning.";
            $incidencia_ya_asignada = true;
        }

	}
	else {
		$return["mensaje"]="El caso ya ha sido atendido.";
        $return["status"] = "warning.";
		$incidencia_ya_atendida = true;
	}

	$return["agente_id"]=$agente_id;
	$return["incidencia_ya_atendida"] = $incidencia_ya_atendida;
	$return["incidencia"] = $objeto;

}

if(isset($_POST["set_incidencias_solve"])){
	
	$msg_bad_incidencia = [];
	$delimiter_log = '-----------------------------------------------------------------------------------------------------';
	$msg_bad_incidencia[] = $delimiter_log;
	//log_incidencias($delimiter_log,[1]);
	$agente_id=$login["id"];
	$data=$_POST["set_incidencias_solve"];
	$solucion_txt=$data["solucion_txt"];
	$incidencia_id=$data["incidencia_id"];

	$recomendacion = isset($data["recomendacion"])?$data["recomendacion"]:'';
	$producto = isset($data["producto"]) ? $data["producto"]:'';
	$tipo = isset($data["tipo"]) ? $data["tipo"]:'';

	$equipo_id = isset($data["equipo_id"])?$data["equipo_id"]:'';
	$equipo_temp = isset($data["equipo_id"])?",equipo_id =" .$equipo_id : ",equipo_id = null ";
	$nota_tecnico = isset($data["nota_tecnico"])?$data["nota_tecnico"]:'';
	$nota_soporte = isset($data["nota_soporte"])?$data["nota_soporte"]:'';
	//$periferico = isset($data["periferico"]) ? implode(", ", $data["periferico"]) : '';
	//$periferico_q = $periferico != "" ? ", periferico = '" . implode (", ", $data["periferico"]) . "'" : "" ;

	if($solucion_txt==""){
		$return["error"]="solucion_txt";
		$return["error_msg"]="Debe ingresar Solución";
		$return["error_focus"]="solucion_txt";	
	}
	else if($recomendacion == "Visita Técnica" && $nota_tecnico == ""){
		$return["error"] = "nota_tecnico";
		$return["error_msg"] = "Debe ingresar Nota para el técnico";
		$return["error_focus"] = "nota_tecnico";	
	}
	else{
		$solucion_txt = $mysqli->real_escape_string($data["solucion_txt"]);
		$nota_tecnico = $mysqli->real_escape_string($data["nota_tecnico"]);
		$nota_soporte = $mysqli->real_escape_string($data["nota_soporte"]);

		$update_command = "
			UPDATE tbl_soporte_incidencias set 
				estado=1
				,updated_at=now()
				,update_user_id= $agente_id
				,solucion_txt=  '$solucion_txt'
				,recomendacion =  '$recomendacion'
				$equipo_temp
				,nota_tecnico =  '$nota_tecnico'
				,nota_soporte =  '$nota_soporte'
				,producto =  '$producto'
				,tipo =  '$tipo'
			where id=".$incidencia_id;

		$mysqli->query($update_command);
		if($mysqli->error){
			print_r($mysqli->error);
			log_incidencias($mysqli->error,[2]); // 1 -> /incidencias.log  2 -> /incidencias_bad.log 
			echo "\n";
			echo $update_command;
			log_incidencias($update_command,[2]); // 1 -> /incidencias.log  2 -> /incidencias_bad.log 
			exit();
		}
		/*mail*/
		//log_incidencias("get incidencia data",[1]); // 1 -> /incidencias.log  2 -> /incidencias_bad.log
		$msg_bad_incidencia[] = "get incidencia $incidencia_id data";
		$command ="
  			SELECT 
  				inc.id AS 'ID de incidencia',
				inc.update_user_at AS 'Fecha y hora de incidencia',
				l.nombre AS 'Tienda',
				l.email AS 'tienda_email',
				u.usuario AS 'Usuario',
				inc.incidencia_txt AS 'Descripción de la incidencia',
				inc.solucion_txt AS 'Observación',
				ste.nombre AS 'equipo',
				u_soporte.usuario AS 'Agente de Soporte',
				( SELECT psop.correo
                FROM tbl_usuarios_locales ul
                LEFT JOIN tbl_locales l_sup on l_sup.id = ul.local_id
                LEFT JOIN tbl_usuarios  u_superv ON u_superv.id = ul.usuario_id
                LEFT JOIN tbl_personal_apt psop ON psop.id = u_superv.personal_id
                WHERE
					ul.local_id = l.id
					AND ul.estado = 1
                    AND u_superv.estado = 1
					AND psop.area_id = 21
					AND psop.cargo_id = 4
					AND psop.estado = 1        limit 1       
				) AS 'supervisor_correo',
                papt.correo AS jefe_zona_correo
				FROM tbl_soporte_incidencias inc
				LEFT JOIN tbl_locales l ON l.id = inc.local_id
				LEFT JOIN tbl_usuarios u ON u.id = inc.user_id
				LEFT JOIN tbl_usuarios u_soporte ON u_soporte.id = inc.update_user_id
				LEFT JOIN tbl_servicio_tecnico_equipo ste ON ste.id = inc.equipo_id
                LEFT JOIN tbl_zonas zona ON l.zona_id = zona.id
                LEFT JOIN tbl_personal_apt papt ON zona.jop_id = papt.id
				WHERE inc.id = ".$incidencia_id;
		$incidencia = $mysqli->query($command)->fetch_assoc();
		//log_incidencias($incidencia,[1]);
		$msg_bad_incidencia[] = $incidencia;

		$equipo = $incidencia["equipo"];
		$correo_supervisor = $incidencia["supervisor_correo"];
		$correo_tienda = $incidencia["tienda_email"];
		$correo_jefe_zona = $incidencia["jefe_zona_correo"];
		unset($incidencia["equipo"]);
		unset($incidencia["tienda_email"]);
		unset($incidencia["tienda_email"]);
		unset($incidencia["jefe_zona_correo"]);
		$body = "<table>";
		$body .= "<tbody>";
		foreach ($incidencia as $key => $value) {
			$body .= "<tr><td><b>" . $key. " :</b></td><td>" .$value ."</td><tr>";
		}
		if($recomendacion == "Visita Técnica")
		{
			$body .= "<tr><td><b>Equipo a revisar :</b></td><td> " .$equipo ."</td></tr>";
			//$body .= $periferico != "" ? "<tr><td><b>Periférico : </b></td><td>" .$periferico ."</td></tr>" : "";
			$body .= "<tr><td><b>Nota para el técnico : </b></td><td>" .$nota_tecnico ."</td></tr>";
		}
		//-------------------------------
		elseif($recomendacion == "Seguimiento Soporte" && $nota_soporte != "")
       {
           $body .= "<tr><td><b>Nota: </b></td><td>" .$nota_soporte ."</td></tr>";
       }
		//--------------------------------
		$body .= "</tbody>";
		$body .= "</table>";

		$cc = [];

		if($correo_supervisor != "" && $recomendacion != "")
		{
		    if(preg_match('/^[_ña-z0-9-]+(\.[_ña-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/',trim($correo_supervisor)) != 0){//correo valid
				$cc[] = $correo_supervisor;
			}
		}
		if($correo_jefe_zona != "" && $recomendacion == "Seguimiento Soporte")
		{
		    if(preg_match('/^[_ña-z0-9-]+(\.[_ña-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/',trim($correo_jefe_zona)) != 0){//correo valid
				$cc[] = $correo_jefe_zona;
			}
		}
		$bcc = [
		];
		switch ($recomendacion)
		{
			case "Visita Técnica":
				$cc[] = "victor.alayo@apuestatotal.com";
				$cc[] = "jose.jimenez@apuestatotal.com";
				$cc[] = "jose.rumay@apuestatotal.com";
			break;
			case "Proveedor de Internet":
				$cc[] = "victor.delgado@apuestatotal.com";
			break;
			case "Capacitación":
				$cc[] = "capacitacion@apuestatotal.com";
			break;
			case "Seguimiento Soporte":
				$cc[] = "soporte@apuestatotal.com";
				if( $correo_tienda != "" ){
					$cc[] = $correo_tienda;
				}
				$bcc[] = "cinthya.guerreros@kurax.dev";
				$bcc[] = "gorqui.chavez@kurax.dev";
			break;
		}
		$mail = [
			"subject" => "Recomendación de Soporte | ID incidencia: ".$incidencia_id." | " . $recomendacion . " | ".$incidencia["Tienda"] ,
			"body"    => $body,
			"cc"      => $cc,
			"bcc"     => $bcc,
		];
		//log_incidencias( $mail,[1] );
		$msg_bad_incidencia[] =  $mail;

		$mail['Host'] = env('MAIL_GESTION_NET_HOST');
		$mail['Username'] = env('MAIL_GESTION_NET_USER');
		$mail['Password'] = env('MAIL_GESTION_NET_PASS');
		$mail['From'] = 'gestion@apuestatotal.net';
		$mail['FromName'] = env('MAIL_GESTION_NET_NAME');
		// $mail['Port'] = env('MAIL_GESTION_NET_PORT');

		if (count($cc) == 0)
		{
			//log_incidencias("No hay destinatarios",[1]);
			/*$msg_bad_incidencia[] = "No hay destinatarios";
			foreach ($msg_bad_incidencia as  $msg_value) {
				log_incidencias($msg_value,[2]);
			}*/
			$return["mensaje"] = "Incidencia " . $incidencia_id . " Cerrada";
		}
		else
		{
			//log_incidencias("Enviando Mail...",[1]);
			$msg_bad_incidencia[] =  "Enviando Mail...";
			ob_start();
			send_email_v6($mail);
			$msg = ob_get_contents();
			ob_end_clean();
			$return["mensaje"] = "Incidencia ".$incidencia_id.": Atendida";
			if($msg != "")
			{
				$return["mensaje"] .= "\n".$msg;
				foreach ($msg_bad_incidencia as  $msg_value) {
					log_incidencias($msg_value,[2]);
				}
	 			log_incidencias("Error al enviar mail : ",[2]);
	 			log_incidencias($msg,[2]);
			}
			else
			{
				foreach ($msg_bad_incidencia as  $msg_value) {
					log_incidencias($msg_value,[1]);
				}
	 			log_incidencias("Mail Enviado",[1]);
			}
		}

		if($correo_supervisor == "")
		{
			$return["mensaje"] .= "\nCorreo de Supervisor no definido";
		}
		else
		{
			if(preg_match('/^[_ña-z0-9-]+(\.[_ña-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/',trim($correo_supervisor)) == 0){//correo invalid
				$return["mensaje"] .= "\n'".$correo_supervisor."' no es un Correo válido";
		    }
	    }

		$return["cc"] = $cc;
		$return["curr_login"]=$login;
		/**/
	}
}

if(isset($_POST["sec_incidencias_notas_save"])){
	$data=$_POST["sec_incidencias_notas_save"];

	if($_POST["nota_txt"]==""){
			$return["error"]="nota_txt";
			$return["error_msg"]="Debe ingresar Nota";
			$return["error_focus"]="nota_txt";	

	}
    else{
		$path = "/var/www/html/files_bucket/incidencia_notas/";
        if (!is_dir($path)) mkdir($path, 0777, true);

		$nombre_archivo = "";
        if($_FILES['nota_imagen']['name'] != ""){
	        $_file = $_FILES['nota_imagen'];
	        $filename = $_file['tmp_name'];
	        $size = $_file['size'];
	        $fileExt = pathinfo($_file['name'], PATHINFO_EXTENSION);
	        $nombre_archivo =  date('YmdHis') . "." . $fileExt;

	        $sourceProperties = getimagesize($filename);
	        $uploadImageType = $sourceProperties[2];
	        $sourceImageWith = $sourceProperties[0];
	        $sourceImageHeight = $sourceProperties[1];

	        $file = [];
	        $imageLayer = [];
	        switch ($uploadImageType) {
	            case IMAGETYPE_JPEG:
	                $resourceType = imagecreatefromjpeg($filename);
	                $imageLayer = resizeImage($resourceType, $sourceImageWith, $sourceImageHeight);

	                $file[0] = imagejpeg($imageLayer[0], $path . $nombre_archivo);
	                break;
	            case IMAGETYPE_PNG:
	                $resourceType = imagecreatefrompng($filename);
	                $imageLayer = resizeImage($resourceType, $sourceImageWith, $sourceImageHeight);

	                $file[0] = imagepng($imageLayer[0], $path . $nombre_archivo);
	                break;
	            case IMAGETYPE_GIF:
	                $resourceType = imagecreatefromgif($filename);
	                $imageLayer = resizeImage($resourceType, $sourceImageWith, $sourceImageHeight);
	                $file[0] = imagegif($imageLayer[0], $path . $nombre_archivo);
	                break;
	        }
	        move_uploaded_file($file[0], $path . $nombre_archivo);
        }

		$nota_txt = $mysqli->real_escape_string($_POST["nota_txt"]);
		$insert_command = "
		INSERT INTO tbl_soporte_notas (created_at,user_id,nota_txt,imagen,estado)
		VALUES (now(),".$login["id"].",'".$nota_txt."','".$nombre_archivo."',1)
		";

		$mysqli->query($insert_command);
		if($mysqli->error){
			print_r($mysqli->error);
			echo "\n";
			echo $insert_command;
			exit();
		}
		$return["id"] = $mysqli->insert_id;
		$return["curr_login"]=$login;
		$return["mensaje"] = "Nota Registrada";
	}
}


if(isset($_POST["sec_incidencias_notas_update"])){
	$user_id=$login["id"];
	$data=$_POST["sec_incidencias_notas_update"];
	$nota_txt=$_POST["nota_txt"];
	$nota_id=$_POST["nota_id"];

	if($nota_txt==""){
		$return["error"]="nota_txt";
		$return["error_msg"]="Debe ingresar Nota";
		$return["error_focus"]="nota_txt";	
	}else{
		$imagen_update = "";
        if($_FILES['nota_imagen']['name'] != ""){
			$imagen_actual = $_POST["imagen_actual"];
			$path = "/var/www/html/files_bucket/incidencia_notas/";
        	@unlink($path . $imagen_actual);
	        $_file = $_FILES['nota_imagen'];
	        $filename = $_file['tmp_name'];
	        $size = $_file['size'];
	        $fileExt = pathinfo($_file['name'], PATHINFO_EXTENSION);
	        $nombre_archivo =  date('YmdHis') . "." . $fileExt;

	        $sourceProperties = getimagesize($filename);
	        $uploadImageType = $sourceProperties[2];
	        $sourceImageWith = $sourceProperties[0];
	        $sourceImageHeight = $sourceProperties[1];

	        $file = [];
	        $imageLayer = [];
	        switch ($uploadImageType) {
	            case IMAGETYPE_JPEG:
	                $resourceType = imagecreatefromjpeg($filename);
	                $imageLayer = resizeImage($resourceType, $sourceImageWith, $sourceImageHeight);

	                $file[0] = imagejpeg($imageLayer[0], $path . $nombre_archivo);
	                break;
	            case IMAGETYPE_PNG:
	                $resourceType = imagecreatefrompng($filename);
	                $imageLayer = resizeImage($resourceType, $sourceImageWith, $sourceImageHeight);

	                $file[0] = imagepng($imageLayer[0], $path . $nombre_archivo);
	                break;
	            case IMAGETYPE_GIF:
	                $resourceType = imagecreatefromgif($filename);
	                $imageLayer = resizeImage($resourceType, $sourceImageWith, $sourceImageHeight);
	                $file[0] = imagegif($imageLayer[0], $path . $nombre_archivo);
	                break;
	        }
	        move_uploaded_file($file[0], $path . $nombre_archivo);
	        $imagen_update = ",imagen = '" .$nombre_archivo. "'";
		}

		$nota_txt = $mysqli->real_escape_string($_POST["nota_txt"]);
		$insert_command = "
			UPDATE tbl_soporte_notas set 
				updated_at=now()
				,update_user_id= $user_id
				,nota_txt=  '$nota_txt'
				$imagen_update
			where id=".$nota_id;

		$mysqli->query($insert_command);
		if($mysqli->error){
			print_r($mysqli->error);
			echo "\n";
			echo $insert_command;
			exit();
		}
		$return["mensaje"] = "Nota ".$nota_id.": Actualizada";
		$return["curr_login"]=$login;
	}
}

if(isset($_POST["sec_incidencias_list"])){
	if($login == false){
		$response = array(
		  "login" => $login
		);
		echo json_encode($response);
		die();
	}
	$TABLA="tbl_soporte_incidencias";
	$ID_LOGIN=$login["id"];
	$data=$_POST["sec_incidencias_list"];
	$draw = $_POST['draw'];
	$row = $_POST['start'];
	$rowperpage = $_POST['length']; // Rows display per page
	$columnIndex = $_POST['order'][0]['column']; // Column index
	$columnName = $_POST['columns'][$columnIndex]['data']; // Column name
	$columnSortOrder = $_POST['order'][0]['dir']; // asc or desc
	$searchValue = $_POST['search']['value']; // Search value
	$searchValue = $mysqli->real_escape_string($searchValue);

    $red_id = $_POST['red_id'];
    $red_query = $red_id == "-1" ? "" : " AND loc.red_id = '$red_id' ";

    ## Search
	$searchQuery = " ";
	if($searchValue != ''){
	   $searchQuery = " and (ag.usuario like '%".$searchValue."%' or
	        loc.nombre like '%".$searchValue."%' or
	        inci.id like '%".$searchValue."%' or
	        inci.telefono2 like '%".$searchValue."%' or
	        inci.producto like '%".$searchValue."%' or
	        inci.tipo like '%".$searchValue."%' or
	        inci.teamviewer_id LIKE '%".$searchValue."%' or
	        inci.created_at like '".$searchValue."%' or
	        inci.updated_at like '".$searchValue."%' or
	        inci.update_user_at like '".$searchValue."%' or
	        usu.usuario like'%".$searchValue."%' ) ";
	}
	$sel = $mysqli->query("SELECT count(*) AS allcount FROM $TABLA");
	$records = $sel->fetch_assoc();
	$totalRecords = $records['allcount'];

	$SELECT=" SELECT inci.id , 
			 loc.nombre as local
			,inci.created_at
			,inci.user_id
			,usu_area.id AS 'usuario_area'
			,usu_cargo.id AS 'usuario_cargo'
			,inci.local_id
			,loc.phone
			,inci.telefono2
			,inci.incidencia_txt
			,inci.estado
			,CASE
                WHEN inci.satisfaccion = 0 THEN 'Nada Satisfecho'
                WHEN inci.satisfaccion = 1 THEN 'Poco Satisfecho'
                WHEN inci.satisfaccion = 2 THEN 'Neutral'
                WHEN inci.satisfaccion = 3 THEN 'Muy Satisfecho'
                WHEN inci.satisfaccion = 4 THEN 'Totalmente Satisfecho'
                ELSE null
            END AS satisfaccion
			,CASE 
			WHEN inci.estado=0 THEN 'Nuevo' 
            WHEN inci.estado=2 then if (ag.id=$ID_LOGIN ,'Solucionar','Asignado') 
            ELSE 'Atendido'       
            END AS EstadoCol
			,inci.updated_at
			,inci.update_user_id
			,inci.update_user_at
			,inci.solucion_txt
			,usu.usuario
			,if(inci.estado=0,'Yo lo Hago',ag.usuario) as agente
			,ag.usuario as agente2
			,ag.id as agente_id
			,inci.producto
			,inci.tipo
			,inci.reimpresion
			,inci.teamviewer_id
			,inci.teamviewer_password
			,inci.recomendacion
			FROM tbl_soporte_incidencias inci 
            left join tbl_locales loc on  inci.local_id=loc.id
            left join tbl_usuarios usu on usu.id= inci.user_id
			LEFT JOIN tbl_personal_apt usu_pers on usu_pers.id = usu.personal_id
            LEFT JOIN tbl_areas usu_area on usu_area.id = usu_pers.area_id
            LEFT JOIN tbl_cargos usu_cargo on usu_cargo.id = usu_pers.cargo_id
            left join tbl_usuarios ag on ag.id= inci.update_user_id
			left join tbl_personal_apt usu_age_pers on usu_age_pers.id= ag.personal_id";

	//col 11 estado
	if($_POST["columns"][11]["search"]["value"] != "" && $_POST["columns"][11]["search"]["value"] != "null"){
		$searchQuery.=	" and inci.estado in (".$_POST["columns"][11]["search"]["value"].")";
	}
	$fecha_ini = date("Y-m-d", strtotime("-30 days"));
	$searchQuery .= "AND inci.created_at >= '$fecha_ini'";
	$empQuery=   $SELECT."	WHERE 1 ". $searchQuery . $red_query;
	$sel = $mysqli->query("SELECT count(*) AS allcount FROM (".$empQuery.") AS subquery");
	$records = $sel->fetch_assoc();
	$totalRecordwithFilter = $records['allcount'];

	$limit=" limit ".$row.",".$rowperpage;
	if($rowperpage==-1){
		$limit="";
	}
	$empQuery= $SELECT." WHERE 1 ".$searchQuery . $red_query . " order by ".$columnName." ".$columnSortOrder.$limit;
	$empRecords = $mysqli->query($empQuery);
	$data = array();

	while ($row = $empRecords->fetch_assoc()) {
	   $data[] = $row;
	}

	$response = array(
	  "draw" => $draw,
	  "iTotalRecords" => $totalRecords,
	  "iTotalDisplayRecords" => $totalRecordwithFilter,
	  "aaData" => $data
	);

	echo json_encode($response);
	return;
}

if(isset($_POST["sec_incidencias_csv"])){
    $data = $_POST["sec_incidencias_csv"];
    $start_date = $data["start_date"] ?? date("Y-m-d");
    $end_date = $data["end_date"] ?? date("Y-m-d");
    $login_id = $login["id"];
    $query =
        "
        SELECT
            inci.id,
            loc.nombre AS local,
            inci.created_at,
            inci.user_id,
            inci.local_id,
            inci.incidencia_txt,
            inci.estado,
            CASE
                WHEN inci.satisfaccion = 0 THEN 'Nada Satisfecho'
                WHEN inci.satisfaccion = 1 THEN 'Poco Satisfecho'
                WHEN inci.satisfaccion = 2 THEN 'Neutral'
                WHEN inci.satisfaccion = 3 THEN 'Muy Satisfecho'
                WHEN inci.satisfaccion = 4 THEN 'Totalmente Satisfecho'
                END AS satisfaccion,
            CASE WHEN inci.estado = 0 THEN 'Nuevo'
                 WHEN inci.estado = 2 THEN IF(ag.id = $login_id, 'SolucionarSolucionar', 'Asignado')
                 ELSE 'Atendido' END AS estadocol,
            inci.updated_at,
            inci.update_user_id,
            inci.update_user_at,
            inci.solucion_txt,
            usu.usuario,
            IF(inci.estado = 0, NULL, ag.usuario) AS agente,
            ag.usuario AS agente2,
            ag.id AS agente_id,
			inci.producto,
			inci.recomendacion,
			inci.equipo,
			inci.nota_tecnico,
			z.nombre AS zona_comercial
        FROM
            tbl_soporte_incidencias inci
                LEFT JOIN tbl_locales loc ON inci.local_id = loc.id
                LEFT JOIN tbl_zonas z ON loc.zona_id = z.id
                LEFT JOIN tbl_usuarios usu ON usu.id = inci.user_id
                LEFT JOIN tbl_usuarios ag ON ag.id = inci.update_user_id
                LEFT JOIN tbl_personal_apt usu_age_pers ON usu_age_pers.id = ag.personal_id
        WHERE
            inci.created_at >= '$start_date' AND
            inci.created_at < DATE_ADD('$end_date', INTERVAL 1 DAY)
        ORDER BY
            created_at    
    ";

    $result = $mysqli->query($query);
    $result_data = array();
    while($r = $result->fetch_assoc()){
        $result_data[] = $r;
    }

    if (count($result_data) > 0){
// CSV FILE
        $csv_table = array();
        $csv_table_columns = array(
            "id" => "ID",
            "created_at" => "Fecha y Hora",
            "usuario" => "Usuario",
            "local" => "Tienda",
            "producto" => "Producto",
            "incidencia_txt" => "Incidencia",
            "estadocol" => "Estado",
            "update_user_at" => "Fecha Asignada",
            "agente" => "Agente",
            "estado" => "Estado",
            "updated_at" => "Fecha de Solución",
            "agente2" => "Agente",
            "solucion_txt" => "Observación",
            "recomendacion" => "Recomendación",
            "equipo" => "Equipo a Revisar",
            "nota_tecnico" => "Nota para el técnico",
            "satisfaccion" => "Satisfacción",
            "zona_comercial" => "Zona Comercial"
        );

        $csv_table[] = $csv_table_columns;

        foreach ($result_data as $data_row){
            $csv_table_row = array(
                "id" => $data_row["id"],
                "created_at" => $data_row["created_at"],
                "usuario" => $data_row["usuario"],
                "local" => $data_row["local"],
                "producto" => $data_row["producto"],
                "incidencia_txt" => $data_row["incidencia_txt"],
                "estadocol" => $data_row["estadocol"],
                "update_user_at" => $data_row["update_user_at"],
                "agente" => $data_row["agente"],
                "estado" => $data_row["estadocol"],
                "updated_at" => $data_row["updated_at"],
                "agente2" => $data_row["agente2"],
                "solucion_txt" => $data_row["solucion_txt"],
                "recomendacion" => $data_row["recomendacion"],
                "equipo" => $data_row["equipo"],
                "nota_tecnico" => $data_row["nota_tecnico"],
                "satisfaccion" => $data_row["satisfaccion"],
                "zona_comercial" => $data_row["zona_comercial"]
            );
            $csv_table[] = $csv_table_row;
        }

        if (!file_exists('../export/files_exported/incidencias')) {
            mkdir('../export/files_exported/incidencias', 0777, true);
        }

        ob_clean();
        ob_start();
        $filename = "reporte_incidencias_{$start_date}_{$end_date}_" . date("YmdHis") . ".csv";
        $fp = fopen("../export/files_exported/incidencias/$filename", 'w');
        foreach ($csv_table as $fields) {
            fputcsv($fp, $fields);
        }
        fclose($fp);

        $response = array(
            "path" => "/export/files_exported/incidencias/$filename"
        );

        echo json_encode($response);
        return;
    }else{
        $response = array(
            "error" => true,
            "error_msg" => "No hay registros en estas fechas",
            "query" => $query,
        );
        echo json_encode($response);
        return;
    }
}

if(isset($_POST["set_incidencias_satisfaccion"])){
    global $mysqli;
    $data = $_POST["set_incidencias_satisfaccion"];
    $query = "UPDATE tbl_soporte_incidencias SET satisfaccion = '$data[value]' WHERE id = '$data[incidencia_id]'";
    $mysqli->query($query);
    $return = true;
    if($mysqli->error){
        $return = false;
    }

    echo json_encode($return);
    return;
}

$return["memory_end"]=memory_get_usage();
$return["time_end"] = microtime(true);
$return["memory_total"]=($return["memory_end"]-$return["memory_init"]);
$return["time_total"]=($return["time_end"]-$return["time_init"]);
print_r(json_encode($return));
?>