<?php
// Админ-панель для JANART STUDIO
$galleryFile = 'gallery-data.json';
$galleryDataRaw = [];
$galleryData = [];
$galleryDataMode = 'gallery';
if (file_exists($galleryFile)) {
    $galleryDataRaw = json_decode(file_get_contents($galleryFile), true) ?: [];
    if (isset($galleryDataRaw['gallery']['paintings']) && is_array($galleryDataRaw['gallery']['paintings'])) {
        $galleryData = $galleryDataRaw['gallery']['paintings'];
        $galleryDataMode = 'gallery';
    } elseif (isset($galleryDataRaw['paintings']) && is_array($galleryDataRaw['paintings'])) {
        $galleryData = $galleryDataRaw['paintings'];
        $galleryDataMode = 'plain';
    } elseif (is_array($galleryDataRaw)) {
        $galleryData = $galleryDataRaw;
        $galleryDataMode = 'plain';
    }

    foreach ($galleryData as &$item) {
        if (is_array($item)) {
            if (!isset($item['lotId'])) {
                $item['lotId'] = $item['id'] ?? uniqid();
            }
            if (!isset($item['quantity'])) {
                $item['quantity'] = 1;
            }
        }
    }
    unset($item);
}

function saveGalleryDataFile(array $galleryData, array $galleryDataRaw, string $galleryDataMode, string $galleryFile): void
{
    if ($galleryDataMode === 'gallery') {
        $galleryDataRaw['gallery']['paintings'] = $galleryData;
    } else {
        $galleryDataRaw['paintings'] = $galleryData;
    }
    file_put_contents($galleryFile, json_encode($galleryDataRaw, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Обработка сохранения данных
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $newItem = [
                    'id' => uniqid(),
                    'title' => $_POST['title'],
                    'description' => $_POST['description'],
                    'price' => $_POST['price'],
                    'quantity' => max(0, intval($_POST['quantity'] ?? 1)),
                    'images' => [],
                    'lotId' => uniqid()
                ];
                $galleryData[] = $newItem;
                break;
            case 'update':
                $id = $_POST['id'];
                $quantity = max(0, intval($_POST['quantity'] ?? 0));
                foreach ($galleryData as &$item) {
                    if ($item['id'] === $id) {
                        $item['quantity'] = $quantity;
                        break;
                    }
                }
                unset($item);
                break;
            case 'edit':
                $id = $_POST['id'];
                foreach ($galleryData as &$item) {
                    if ($item['id'] === $id) {
                        $item['title'] = $_POST['title'];
                        $item['description'] = $_POST['description'];
                        $item['price'] = $_POST['price'];
                        $item['quantity'] = max(0, intval($_POST['quantity'] ?? ($item['quantity'] ?? 1)));
                        break;
                    }
                }
                unset($item);
                break;
            case 'delete':
                $id = $_POST['id'];
                $galleryData = array_filter($galleryData, function($item) use ($id) {
                    return $item['id'] !== $id;
                });
                $galleryData = array_values($galleryData);
                break;
        }
        
        saveGalleryDataFile($galleryData, $galleryDataRaw, $galleryDataMode, $galleryFile);
        header('Location: ?admin=12345');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель - JANART STUDIO</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
        }

        .admin-header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-header h1 {
            margin: 0;
            font-size: 24px;
        }

        .admin-close {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }

        .admin-close:hover {
            background: #c0392b;
        }

        .admin-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .admin-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .admin-section h2 {
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            background: #2980b9;
        }

        .btn-danger {
            background: #e74c3c;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-success {
            background: #27ae60;
        }

        .btn-success:hover {
            background: #229954;
        }

        .gallery-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .gallery-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            background: #f9f9f9;
        }

        .gallery-item h3 {
            margin-top: 0;
            color: #2c3e50;
        }

        .gallery-item img {
            max-width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 4px;
            margin-bottom: 10px;
        }

        .gallery-item .actions {
            margin-top: 15px;
        }

        .gallery-item .actions .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            background: #f9f9f9;
        }

        .upload-area.dragover {
            border-color: #3498db;
            background: #ecf0f1;
        }

        .upload-area input[type="file"] {
            display: none;
        }

        .upload-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
        }

        .upload-btn:hover {
            background: #2980b9;
        }

        .image-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .image-preview img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
            border: 2px solid #ddd;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            margin: 0;
            font-size: 36px;
            color: #3498db;
        }

        .stat-card p {
            margin: 5px 0 0;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>Админ-панель JANART STUDIO</h1>
        <a href="index.php" class="admin-close">← Вернуться на сайт</a>
    </div>

    <div class="admin-content">
        <!-- Статистика -->
        <div class="stats">
            <div class="stat-card">
                <h3><?php echo count($galleryData); ?></h3>
                <p>Всего работ</p>
            </div>
            <div class="stat-card">
                <h3><?php echo array_sum(array_map(function($item) { return count($item['images']); }, $galleryData)); ?></h3>
                <p>Всего изображений</p>
            </div>
        </div>

        <!-- Добавление новой работы -->
        <div class="admin-section">
            <h2>Добавить новую работу</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">Название работы</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="price">Цена (₽)</label>
                        <input type="number" id="price" name="price" required>
                    </div>
                    <div class="form-group">
                        <label for="quantity">Количество</label>
                        <input type="number" id="quantity" name="quantity" value="1" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Описание</label>
                    <textarea id="description" name="description" required></textarea>
                </div>

                <div class="form-group">
                    <label>Изображения</label>
                    <div class="upload-area" id="uploadArea">
                        <p>Перетащите изображения сюда или нажмите для выбора</p>
                        <input type="file" id="imageInput" multiple accept="image/*">
                        <button type="button" class="upload-btn" onclick="document.getElementById('imageInput').click()">
                            Выбрать файлы
                        </button>
                    </div>
                    <div class="image-preview" id="imagePreview"></div>
                </div>

                <button type="submit" class="btn btn-success">Добавить работу</button>
            </form>
        </div>

        <!-- Список работ -->
        <div class="admin-section">
            <h2>Управление работами</h2>
            <div class="gallery-list">
                <?php foreach ($galleryData as $item): ?>
                <div class="gallery-item">
                    <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                    <?php if (!empty($item['images'])): ?>
                        <img src="<?php echo htmlspecialchars($item['images'][0]); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                    <?php endif; ?>
                    <p><strong>Описание:</strong> <?php echo htmlspecialchars($item['description']); ?></p>
                    <p><strong>Цена:</strong> <?php echo htmlspecialchars($item['price']); ?> ₽</p>
                    <p><strong>Количество:</strong> <?php echo (int)($item['quantity'] ?? 1); ?></p>
                    <form method="POST" style="display: inline-block; margin-bottom: 10px;">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                        <label style="font-weight: bold; margin-right: 8px;">Изменить количество:</label>
                        <input type="number" name="quantity" value="<?php echo (int)($item['quantity'] ?? 1); ?>" min="0" style="width: 80px; margin-right: 8px;">
                        <button type="submit" class="btn btn-success">Сохранить</button>
                    </form>
                    <p><strong>Изображений:</strong> <?php echo count($item['images']); ?></p>
                    <div class="actions">
                        <button class="btn" onclick="editItem('<?php echo $item['id']; ?>')">Редактировать</button>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Удалить эту работу?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                            <button type="submit" class="btn btn-danger">Удалить</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        // Обработка загрузки изображений
        const uploadArea = document.getElementById('uploadArea');
        const imageInput = document.getElementById('imageInput');
        const imagePreview = document.getElementById('imagePreview');

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            handleFiles(files);
        });

        imageInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        function handleFiles(files) {
            Array.from(files).forEach(file => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.width = '100px';
                        img.style.height = '100px';
                        img.style.objectFit = 'cover';
                        img.style.borderRadius = '4px';
                        img.style.border = '2px solid #ddd';
                        imagePreview.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // Функция редактирования (заглушка)
        function editItem(id) {
            alert('Функция редактирования будет добавлена в следующей версии');
        }
    </script>
</body>
</html>
