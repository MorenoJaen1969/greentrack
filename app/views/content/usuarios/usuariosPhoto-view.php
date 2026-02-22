<?php

$origen = $url[1];
$id_user = $url[2];
$pagina = $url[4];

$id_login = $usuarios->limpiarCadena($url[2]);
$rows = $usuarios->seleccionarDatos($id_login);

$id_login = $rows['id'];
$nombre = $rows['nombre'];
$creado = $rows['fecha_registro'];
$usuario_foto = $rows['usuario_foto'];

$photo_default = "/app/views/fotos/default.png";
?>

<main>
	<div class="container-principal">
		<div class="row">
			<h2 style="text-align:center"><span class="fa-solid fa-user">&nbsp</span>Update profile picture</h2>
		</div>
		<br>
		<div class="container pb-1 pt-1">
			<div class="container pb-1 pt-1">
				<h2 class="title has-text-centered">
					<?php echo $nombre; ?>
				</h2>

				<p class="has-text-centered pb-1">
					<?php echo "<strong>User created on date: </strong> " . date("d-m-Y  h:i:s A", strtotime($creado)) ?>
				</p>
			</div>
		</div>
	</div>

	<div class="container pb-1 pt-1">
		<div class="columns">
			<div class="column is-two-fifths">
				<?php if (!empty($usuario_foto)) { ?>
					<?php if (is_file("./app/views/fotos/" . $usuario_foto)) { ?>
						<figure class="image mb-6">
							<img class="is-rounded" src="<?php echo APP_URL; ?>/app/views/fotos/<?php echo $usuario_foto; ?>">
						</figure>

						<form class="FormularioAjax" action="<?php echo APP_URL; ?>/app/ajax/usuariosAjax.php" method="POST" autocomplete="off">

							<input type="hidden" name="modulo_usuario" value="eliminarFoto">
							<input type="hidden" name="usuario_id" value="<?php echo $id_login; ?>">

							<p class="has-text-centered">
								<button type="submit" class="button is-danger is-rounded">Delete photo</button>
							</p>
						</form>
					<?php } else { ?>
						<figure class="image mb-6">
							<img class="is-rounded" src="<?php echo $photo_default; ?>">
						</figure>
					<?php } ?>
				<?php } else { ?>
					<figure class="image mb-6">
						<img class="is-rounded" src="<?php echo $photo_default; ?>">
					</figure>
				<?php } ?>
			</div>


			<div class="column">
				<form class="mb-6 has-text-centered FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/usuariosAjax.php" method="POST" enctype="multipart/form-data" autocomplete="off">

					<input type="hidden" name="modulo_usuario" value="actualizarFoto">
					<input type="hidden" name="usuario_id" value="<?php echo $id_login; ?>">

					<label>User photo or image</label><br>

					<div class="file has-name is-boxed is-justify-content-center mb-6">
						<label for="usuario_foto" class="file-label">
							<input class="file-input" type="file" id="usuario_foto" name="usuario_foto" accept=".jpg, .png, .jpeg">
							<span class="file-cta">
								<span class="file-label">
									Select a photo
								</span>
							</span>
							<span class="file-name">JPG, JPEG, PNG. (MAX 5MB)</span>
						</label>
					</div>
					<p class="has-text-centered">
						<button type="submit" class="button is-success is-rounded">Update photo</button>
					</p>
				</form>
			</div>
		</div>
	</div>
	</div>
</main>