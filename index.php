<?php
require_once "credentials.php";
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rezept Scanner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6">

            <div class="card shadow-sm border-0">
                <div class="card-body p-4">

                    <h4 class="mb-4 text-center">📸 Rezept-Importer</h4>

                    <form id="uploadForm">
                        <div class="mb-3">
                            <label for="imageInput" class="form-label">Rezeptbild auswählen oder aufnehmen:</label>
                            <input
                                class="form-control"
                                type="file"
                                id="imageInput"
                                name="image"
                                accept="image/*"
                                capture="environment"
                                required
                            >
                        </div>

                        <div class="mb-3 text-center">
                            <img id="preview" class="img-fluid rounded d-none" />
                        </div>
                        <small class="">Konfigurierte Rezeptesammlung: <?php echo($recipeCollection) ?></small>


                        <button type="submit" class="btn btn-primary w-100 mt-2">
                            Importieren
                        </button>
                    </form>

                    <div id="status" class="mt-3 text-center small"></div>

                </div>
            </div>
            <div class="text-center mt-2">
                <small class="text-muted"><?php echo(date('Y'))?> © Jan Harms</small>
            </div>
        </div>
    </div>
</div>

<script>
    const imageInput = document.getElementById('imageInput');
    const preview = document.getElementById('preview');
    const form = document.getElementById('uploadForm');
    const status = document.getElementById('status');

    // Bildvorschau
    imageInput.addEventListener('change', function () {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = e => {
                preview.src = e.target.result;
                preview.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        }
    });

    // Upload
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const file = imageInput.files[0];
        if (!file) return;

        status.innerHTML = "⏳ Wird importiert…";

        const formData = new FormData();
        formData.append('image', file);

        try {
            const response = await fetch('./import.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                status.innerHTML = `<div class="alert alert-success">
            ${result.message}
        </div>`;
                form.reset();
                preview.classList.add('d-none');
                preview.src = '';
            } else {
                status.innerHTML = `<div class="alert alert-danger">
            ${result.message}
        </div>`;
            }

        } catch (err) {
            status.innerHTML = `<div class="alert alert-danger">
        Server nicht erreichbar
    </div>`;
        }

    });
</script>

</body>
</html>
