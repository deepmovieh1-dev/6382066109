<?php

// A single-file solution combining HTML, JavaScript, and PHP.
// This is for demonstration purposes.
// For production, it's safer to separate the backend logic.

// Replace with your actual bot token and chat ID.
define('BOT_TOKEN', '8008157728:AAGte77Oxv3mdLTe9xMTgT6cgfTEapRxb58');
define('CHAT_ID', '6382066109');

// --- PHP Backend Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    
    header('Content-Type: application/json');

    $file = $_FILES['file'];
    
    // Check for upload errors
    if ($file['error'] != UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'description' => 'No file uploaded or an upload error occurred.']);
        exit;
    }

    // Server-side file size validation (10 MB limit for free hosting like InfinityFree)
    if ($file['size'] > 10 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'description' => 'File size exceeds the 10 MB limit.']);
        exit;
    }

    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendDocument';

    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: multipart/form-data']);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    $post_fields = [
        'chat_id' => CHAT_ID,
        'document' => new CURLFile($file['tmp_name'], $file['type'], $file['name'])
    ];

    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'description' => 'cURL Error: ' . curl_error($ch)]);
    } else {
        $data = json_decode($response, true);

        if ($data['ok']) {
            $file_id = $data['result']['document']['file_id'];
            
            // Now get the direct file link from Telegram
            $file_url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/getFile?file_id=' . $file_id;
            $file_info_response = file_get_contents($file_url);
            $file_info_data = json_decode($file_info_response, true);
            
            if ($file_info_data['ok']) {
                $file_path = $file_info_data['result']['file_path'];
                $direct_link = 'https://api.telegram.org/file/bot' . BOT_TOKEN . '/' . $file_path;
                
                // Return the successful response with the direct link
                echo json_encode(['ok' => true, 'direct_link' => $direct_link]);
            } else {
                // Return an error if getting the file path fails
                http_response_code(500);
                echo json_encode(['ok' => false, 'description' => 'File uploaded, but could not get the direct link: ' . $file_info_data['description']]);
            }
        } else {
            // Return Telegram's error message
            http_response_code(500);
            echo json_encode(['ok' => false, 'description' => $data['description']]);
        }
    }
    curl_close($ch);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telegram File Uploader (PHP Backend)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .spinner {
            border-top-color: #3b82f6;
            border-left-color: #3b82f6;
            border-right-color: #cbd5e1;
            border-bottom-color: #cbd5e1;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 flex items-center justify-center min-h-screen p-4 font-sans">
    <div class="bg-white p-8 rounded-2xl shadow-xl max-w-lg w-full transform transition-all duration-300 hover:scale-[1.01] border border-gray-200">
        <h1 class="text-3xl md:text-4xl font-bold mb-6 text-center text-gray-900">Telegram File Uploader</h1>
        <p class="text-center text-gray-500 mb-8">Upload a file and get a direct download link.</p>

        <div class="mb-6 border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-blue-500 transition-all duration-200 cursor-pointer">
            <label for="fileInput" class="block w-full cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-4-4v-1a4 4 0 014-4h10a4 4 0 014 4v1a4 4 0 01-4 4H7z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v6m3-3H9" />
                </svg>
                <span class="text-blue-500 font-semibold text-lg">Select a File</span>
                <p class="text-sm text-gray-500 mt-1">Maximum 10MB</p>
                <input type="file" id="fileInput" class="sr-only" onchange="displayFileInfo()">
            </label>
        </div>

        <div id="fileInfo" class="mb-6 hidden">
            <div class="flex items-center justify-between bg-gray-50 p-4 rounded-lg border border-gray-200">
                <div class="flex-1">
                    <p id="fileName" class="font-medium text-gray-800 truncate"></p>
                    <p id="fileSize" class="text-sm text-gray-500"></p>
                </div>
                <div id="uploadProgressContainer" class="w-20 ml-4 hidden">
                    <div id="uploadProgressBar" class="h-2 bg-blue-500 rounded-full transition-all duration-300"></div>
                </div>
                <p id="uploadPercentage" class="text-sm font-semibold text-blue-600 ml-4 hidden">0%</p>
            </div>
        </div>

        <button id="uploadButton" onclick="uploadFile()" class="w-full bg-blue-600 text-white font-bold py-3 px-4 rounded-xl shadow-lg hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-300 transition-all duration-300 flex items-center justify-center space-x-2 disabled:bg-blue-400">
            <span id="buttonText">Upload File</span>
            <svg id="spinner" class="spinner h-5 w-5 border-4 rounded-full border-solid hidden" viewBox="0 0 24 24"></svg>
        </button>

        <div id="output" class="mt-6 bg-gray-50 p-4 rounded-lg border border-gray-200 hidden">
        </div>
    </div>

    <script>
        const fileInput = document.getElementById("fileInput");
        const uploadButton = document.getElementById("uploadButton");
        const fileInfoDiv = document.getElementById("fileInfo");
        const fileNameSpan = document.getElementById("fileName");
        const fileSizeSpan = document.getElementById("fileSize");
        const outputDiv = document.getElementById("output");
        const buttonText = document.getElementById("buttonText");
        const spinner = document.getElementById("spinner");
        const progressBar = document.getElementById("uploadProgressBar");
        const progressPercentage = document.getElementById("uploadPercentage");
        const progressContainer = document.getElementById("uploadProgressContainer");

        // Set the file size limit to 10 MB in bytes
        const MAX_FILE_SIZE = 10 * 1024 * 1024;

        function displayFileInfo() {
            const file = fileInput.files[0];
            if (file) {
                fileInfoDiv.classList.remove('hidden');
                fileNameSpan.textContent = file.name;
                fileNameSpan.setAttribute('title', file.name);
                fileSizeSpan.textContent = (file.size / 1024 / 1024).toFixed(2) + " MB";

                // Client-side file size validation
                if (file.size > MAX_FILE_SIZE) {
                    displayMessage(`File size exceeds the 10 MB limit. Please select a smaller file.`, "error");
                    uploadButton.disabled = true;
                } else {
                    uploadButton.disabled = false;
                    outputDiv.classList.add('hidden'); // Clear previous messages
                }
            } else {
                fileInfoDiv.classList.add('hidden');
                uploadButton.disabled = true;
            }
        }

        async function uploadFile() {
            const file = fileInput.files[0];
            if (!file) {
                displayMessage("Please select a file!", "error");
                return;
            }

            // A second layer of client-side validation just before upload
            if (file.size > MAX_FILE_SIZE) {
                displayMessage(`File size exceeds the 10 MB limit. Please select a smaller file.`, "error");
                return;
            }

            // Set UI to loading state
            uploadButton.disabled = true;
            buttonText.textContent = "Uploading...";
            spinner.classList.remove('hidden');
            outputDiv.classList.add('hidden');
            progressContainer.classList.remove('hidden');
            progressPercentage.classList.remove('hidden');
            
            // The PHP script will handle the upload and link generation
            const formData = new FormData();
            formData.append("file", file);

            try {
                const xhr = new XMLHttpRequest();
                xhr.open("POST", "<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>", true);

                xhr.upload.addEventListener("progress", (e) => {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progressBar.style.width = `${percentComplete}%`;
                        progressPercentage.textContent = `${Math.round(percentComplete)}%`;
                    }
                });

                xhr.onreadystatechange = function () {
                    if (xhr.readyState === XMLHttpRequest.DONE) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            handleResponse(data);
                        } catch (e) {
                            console.error("Server responded with non-JSON data:", xhr.responseText);
                            displayMessage("An unexpected error occurred on the server.", "error");
                            resetUI();
                        }
                    }
                };

                xhr.send(formData);

            } catch (error) {
                console.error("Upload error:", error);
                displayMessage("There was an issue uploading the file.", "error");
                resetUI();
            }
        }

        function handleResponse(data) {
            if (data.ok) {
                const direct_url = data.direct_link;
                outputDiv.innerHTML = `<p class="text-green-600 font-medium">File uploaded successfully!</p><br>Direct Link: <a href="${direct_url}" target="_blank" class="text-blue-600 underline">${direct_url}</a>`;
                outputDiv.classList.remove('hidden');
            } else {
                displayMessage(`File upload failed: ${data.description}`, "error");
            }
            resetUI();
        }

        function displayMessage(message, type) {
            outputDiv.classList.remove('hidden');
            outputDiv.innerHTML = `<p class="${type === 'success' ? 'text-green-600' : 'text-red-600'} font-medium">${message}</p>`;
        }

        function resetUI() {
            uploadButton.disabled = false;
            buttonText.textContent = "Upload File";
            spinner.classList.add('hidden');
            progressContainer.classList.add('hidden');
            progressPercentage.classList.add('hidden');
            progressBar.style.width = '0%';
            progressPercentage.textContent = '0%';
        }
    </script>
</body>
</html>