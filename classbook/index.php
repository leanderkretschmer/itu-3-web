<?php
// DB-Verbindung
require_once '../db_connect.php';

// Abrufen der Fächer
function getSubjects($conn) {
    $query = "SELECT * FROM faecher";
    $result = $conn->query($query);

    // Überprüfe, ob die Abfrage erfolgreich war
    if (!$result) {
        die("Datenbankabfrage fehlgeschlagen: " . $conn->error . " Abfrage: " . $query);
    }

    $faecher = [];
    while ($row = $result->fetch_assoc()) {
        $faecher[] = $row;
    }

    return $faecher;
}

// Abrufen der Themen
function getTopics($conn, $fachId) {
    $query = "SELECT * FROM themen WHERE fach_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $fachId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $themen = [];
    while ($row = $result->fetch_assoc()) {
        $themen[] = $row;
    }

    return $themen;
}

// Abrufen der Unterthemen
function getSubtopics($conn, $themaId) {
    $query = "SELECT * FROM unterthemen WHERE thema_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $themaId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $unterthemen = [];
    while ($row = $result->fetch_assoc()) {
        $unterthemen[] = $row;
    }

    return $unterthemen;
}

// Neues Fach speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fach_name'])) {
    $fach_name = $_POST['fach_name'];
    $query = "INSERT INTO faecher (name) VALUES (?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $fach_name);
    if ($stmt->execute()) {
        echo "Fach erfolgreich erstellt!";
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassBook</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="w-1/4 bg-white p-4 overflow-y-auto border-r">
            <h2 class="text-lg font-bold mb-4">Fächer</h2>
            <ul id="subjectList" class="space-y-2">
                <!-- Fächer werden hier dynamisch geladen -->
            </ul>
            <button id="createSubjectButton" class="w-full mt-4 text-white bg-blue-500 p-2 rounded">
                Neues Fach erstellen
            </button>
            <!-- Formular zum Erstellen eines neuen Fachs -->
            <div id="createSubjectForm" class="mt-4 hidden">
                <input id="fachName" class="w-full p-2 border" type="text" placeholder="Fachname eingeben">
                <button id="saveSubjectButton" class="w-full mt-2 text-white bg-green-500 p-2 rounded">Fach speichern</button>
            </div>
        </div>
        
        <!-- Hauptinhalt -->
        <div class="flex-1 p-6">
            <h1 class="text-2xl font-bold" id="contentTitle">Willkommen im ClassBook</h1>
            <p class="mt-4 text-gray-700" id="contentText">Wähle ein Fach oder Thema aus der linken Leiste.</p>
        </div>
    </div>
    
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            fetchSubjects();  // Fächer beim Laden der Seite anzeigen

            // Button zum Erstellen eines neuen Fachs anzeigen
            const createSubjectButton = document.getElementById("createSubjectButton");
            const createSubjectForm = document.getElementById("createSubjectForm");

            createSubjectButton.addEventListener("click", () => {
                createSubjectForm.classList.toggle("hidden");
            });

            // Button zum Speichern eines neuen Fachs
            const saveSubjectButton = document.getElementById("saveSubjectButton");
            saveSubjectButton.addEventListener("click", () => {
                const fachName = document.getElementById("fachName").value;
                if (fachName) {
                    createSubject(fachName);
                }
            });
        });

        // Fächer abrufen
        function fetchSubjects() {
            fetch("<?php echo $_SERVER['PHP_SELF']; ?>")
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        document.getElementById("subjectList").innerHTML = "<p>Keine Fächer gefunden</p>";
                    } else {
                        let subjectList = document.getElementById("subjectList");
                        data.forEach(subject => {
                            let subjectItem = document.createElement("li");
                            subjectItem.innerHTML = `<button class='w-full text-left font-semibold' onclick='loadTopics(${subject.id})'>${subject.name}</button>`;
                            subjectList.appendChild(subjectItem);
                        });
                    }
                })
                .catch(error => console.error('Fehler beim Laden der Fächer:', error));
        }

        // Themen laden
        function loadTopics(fachId) {
            fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?fach_id=${fachId}`)
                .then(response => response.json())
                .then(data => {
                    let subjectList = document.getElementById("subjectList");
                    subjectList.innerHTML = "";
                    data.forEach(topic => {
                        let topicItem = document.createElement("li");
                        topicItem.innerHTML = `<button class='w-full text-left' onclick='loadSubtopics(${topic.id})'>📂 ${topic.name}</button>`;
                        subjectList.appendChild(topicItem);
                    });
                })
                .catch(error => console.error('Fehler beim Laden der Themen:', error));
        }

        // Unterthemen laden
        function loadSubtopics(themaId) {
            fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?thema_id=${themaId}`)
                .then(response => response.json())
                .then(data => {
                    let subjectList = document.getElementById("subjectList");
                    subjectList.innerHTML = "";
                    data.forEach(subtopic => {
                        let subtopicItem = document.createElement("li");
                        subtopicItem.innerHTML = `<button class='w-full text-left' onclick='loadContent(${subtopic.id})'>📄 ${subtopic.name}</button>`;
                        subjectList.appendChild(subtopicItem);
                    });
                })
                .catch(error => console.error('Fehler beim Laden der Unterthemen:', error));
        }

        // Inhalt laden
        function loadContent(subtopicId) {
            fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?unterthema_id=${subtopicId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById("contentTitle").textContent = data.name;
                    document.getElementById("contentText").textContent = data.content;
                })
                .catch(error => console.error('Fehler beim Laden des Inhalts:', error));
        }

        // Neues Fach erstellen
        function createSubject(fachName) {
            fetch("<?php echo $_SERVER['PHP_SELF']; ?>", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: `fach_name=${fachName}`,
            })
            .then(response => response.text())
            .then(message => {
                alert(message);
                fetchSubjects();  // Neu laden der Fächer
            })
            .catch(error => console.error('Fehler beim Erstellen des Fachs:', error));
        }
    </script>
</body>
</html>
