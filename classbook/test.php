<?php
// DB-Verbindung (z. B. mit mysqli)
require_once '../db_connect.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*
Drei Funktionalitäten:
1. POST-Request für den neuen Wiki-Eintrag via Markdown.
2. GET-Request mit ?action=getWiki, um alle Wiki-Einträge als hierarchische JSON-Struktur auszugeben.
3. Ansonsten wird die HTML-Seite (OnePager) ausgegeben.
*/

// POST: Neuen Wiki-Eintrag verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eintrag_md'])) {
    $markdown = $_POST['eintrag_md'];
    processWikiEntry($conn, $markdown);
    header('Content-Type: application/json');
    echo json_encode(["message" => "Eintrag erfolgreich verarbeitet!"]);
    exit;
}

// GET: Wiki-Einträge als JSON zurückliefern
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'getWiki') {
    header('Content-Type: application/json');
    $entries = getWikiEntries($conn);
    echo json_encode($entries);
    exit;
}

/*
Funktion: processWikiEntry()
Parst den übergebenen Markdown-Text zeilenweise. Jede Zeile,
die mit (“#+”) beginnt, markiert einen neuen Titel auf einem bestimmten
Hierarchielevel – alle folgenden Zeilen ohne führende Hashtags werden als
Inhalt dieses Eintrags interpretiert.
Zur Speicherung greifen wir auf die Tabelle wiki_entries zu.
*/
function processWikiEntry($conn, $markdown) {
    // Den Markdown-Text zeilenweise auseinandernehmen:
    $lines = preg_split('/\r\n|\r|\n/', $markdown);
    $entries = [];
    $currentEntry = null;
    foreach ($lines as $line) {
        if (preg_match('/^(#+)\s*(.*)$/', $line, $matches)) {
            // Überschrift gefunden
            $level = strlen($matches[1]);
            $title = trim($matches[2]);
            if ($currentEntry !== null) {
                $entries[] = $currentEntry;
            }
            // Beginne einen neuen Eintrag:
            $currentEntry = [
                "level"   => $level,
                "title"   => $title,
                "content" => ""
            ];
        } else {
            // Normale Zeile: Hänge diese als Inhalt an den aktuellen Eintrag an
            if ($currentEntry !== null) {
                $currentEntry["content"] .= $line . "\n";
            }
        }
    }
    if ($currentEntry !== null) {
        $entries[] = $currentEntry;
    }

    // Jetzt werden die Einträge verarbeitet – unter Beachtung von Eltern-Kind-Beziehungen.
    // Hier speichern wir den zuletzt eingefügten Eintrag für jedes Level, um
    // so den parent_id zu bestimmen.
    $lastEntryOfLevel = [];

    foreach ($entries as $entry) {
        $level = $entry["level"];
        $title = $entry["title"];
        $content = trim($entry["content"]);

        // Bestimme die parent_id (bei Level 1: kein Parent)
        $parent_id = null;
        if ($level > 1 && isset($lastEntryOfLevel[$level - 1])) {
            $parent_id = $lastEntryOfLevel[$level - 1];
        }

        // Prüfe, ob bereits ein Eintrag mit gleichem Titel, Level und
        // (falls vorhanden) gleicher parent_id existiert.
        if ($parent_id === null) {
            $stmt = $conn->prepare(
                "SELECT id FROM wiki_entries WHERE title = ? AND level = ? AND parent_id IS NULL"
            );
            $stmt->bind_param("si", $title, $level);
        } else {
            $stmt = $conn->prepare(
                "SELECT id FROM wiki_entries WHERE title = ? AND level = ? AND parent_id = ?"
            );
            $stmt->bind_param("sii", $title, $level, $parent_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Der Eintrag existiert bereits – ergänze den Inhalt.
            $entry_id = $row["id"];
            $stmtUpdate = $conn->prepare(
                "UPDATE wiki_entries SET content = CONCAT(content, ?) WHERE id = ?"
            );
            $additional = "\n" . $content;
            $stmtUpdate->bind_param("si", $additional, $entry_id);
            $stmtUpdate->execute();
        } else {
            // Neuer Eintrag: In der Tabelle speichern.
            if ($parent_id === null) {
                $stmtInsert = $conn->prepare(
                    "INSERT INTO wiki_entries (parent_id, level, title, content) VALUES (NULL, ?, ?, ?)"
                );
                $stmtInsert->bind_param("iss", $level, $title, $content);
            } else {
                $stmtInsert = $conn->prepare(
                    "INSERT INTO wiki_entries (parent_id, level, title, content) VALUES (?, ?, ?, ?)"
                );
                $stmtInsert->bind_param("iiss", $parent_id, $level, $title, $content);
            }
            $stmtInsert->execute();
            $entry_id = $stmtInsert->insert_id;
        }

        $lastEntryOfLevel[$level] = $entry_id;
        // Entferne Einträge von tieferen Ebenen, falls vorhanden.
        for ($i = $level + 1; $i <= count($lastEntryOfLevel) + 1; $i++) {
            if (isset($lastEntryOfLevel[$i])) {
                unset($lastEntryOfLevel[$i]);
            }
        }
    }
}

/*
Funktion: getWikiEntries()
Liest alle Wiki-Einträge aus der Tabelle aus und baut daraus eine hierarchische
Struktur (Eltern-Kind-Beziehungen). Die Einträge werden anschliessend
alphabetisch sortiert (auf jeder Ebene).
*/
function getWikiEntries($conn) {
    $query = "SELECT id, parent_id, level, title, content FROM wiki_entries";
    $result = $conn->query($query);
    $entries = [];
    while ($row = $result->fetch_assoc()) {
        $entries[] = $row;
    }
    // Erstelle einen Index, um die Hierarchie aufzubauen.
    $tree = [];
    $byId = [];
    foreach ($entries as $entry) {
        $entry['children'] = [];
        $byId[$entry['id']] = $entry;
    }
    foreach ($byId as $id => &$entry) {
        if ($entry['parent_id']) {
            $byId[$entry['parent_id']]['children'][] = &$entry;
        } else {
            $tree[] = &$entry;
        }
    }
    // Rekursive Sortierung: alphabetisch nach Titel (auf jeder Ebene)
    function sortEntries(&$entries) {
        usort($entries, function ($a, $b) {
            return strcmp($a['title'], $b['title']);
        });
        foreach ($entries as &$entry) {
            if (!empty($entry['children'])) {
                sortEntries($entry['children']);
            }
        }
    }
    sortEntries($tree);
    return $tree;
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Wiki OnePager</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Optional: Stil für das Modal */
    .modal {
      display: none;
    }
    .modal.active {
      display: block;
    }
  </style>
</head>
<body class="bg-gray-100">
  <div class="flex min-h-screen">
    <!-- Seitenleiste -->
    <div class="w-1/4 bg-white p-4 overflow-y-auto border-r">
      <h2 class="text-lg font-bold mb-4">Wiki Übersicht</h2>
      <ul id="wikiList" class="space-y-2">
        <!-- Wiki-Einträge werden hier dynamisch via JS geladen -->
      </ul>
    </div>
    <!-- Hauptinhalt -->
    <div class="flex-1 p-6">
      <h1 class="text-2xl font-bold" id="contentTitle">Willkommen im Wiki</h1>
      <div id="contentArea" class="mt-4 text-gray-700"></div>
    </div>
  </div>

  <!-- Fixierter Button "Eintrag" unten rechts -->
  <button
    id="openEntryButton"
    class="fixed bottom-4 right-4 bg-blue-500 text-white p-4 rounded-full shadow-lg"
  >
    Eintrag
  </button>

  <!-- Modal für den Markdown-Eintrag -->
  <div
    id="entryModal"
    class="modal fixed inset-0 flex items-center justify-center bg-black bg-opacity-50"
  >
    <div class="bg-white p-6 rounded shadow-lg w-11/12 max-w-lg relative">
      <h2 class="text-xl font-bold mb-4">Neuer Wiki Eintrag</h2>
      <textarea
        id="markdownInput"
        class="w-full h-48 p-2 border rounded"
        placeholder="Gib deinen Markdown Text ein..."
      ></textarea>
      <div class="mt-4 flex justify-end space-x-2">
        <button
          id="closeModalButton"
          class="px-4 py-2 bg-gray-300 rounded"
        >
          Schließen
        </button>
        <button
          id="saveEntryButton"
          class="px-4 py-2 bg-green-500 text-white rounded"
        >
          Speichern
        </button>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      loadWikiEntries();

      const openEntryButton = document.getElementById("openEntryButton");
      const entryModal = document.getElementById("entryModal");
      const closeModalButton = document.getElementById("closeModalButton");
      const saveEntryButton = document.getElementById("saveEntryButton");

      openEntryButton.addEventListener("click", () => {
        entryModal.classList.add("active");
      });

      closeModalButton.addEventListener("click", () => {
        entryModal.classList.remove("active");
      });

      saveEntryButton.addEventListener("click", () => {
        const markdown =
          document.getElementById("markdownInput").value;
        if (markdown.trim() === "") {
          alert("Bitte einen Eintrag eingeben.");
          return;
        }
        // Markdown via POST absenden
        fetch("<?php echo $_SERVER['PHP_SELF']; ?>", {
          method: "POST",
          headers: {
            "Content-Type":
              "application/x-www-form-urlencoded;charset=UTF-8"
          },
          body: "eintrag_md=" + encodeURIComponent(markdown)
        })
          .then((response) => response.json())
          .then((data) => {
            alert(data.message);
            document.getElementById("markdownInput").value = "";
            entryModal.classList.remove("active");
            loadWikiEntries();
          })
          .catch((error) => {
            console.error("Fehler beim Speichern des Eintrags:", error);
          });
      });
    });

    // Lädt alle Wiki-Einträge via AJAX, baut die Sidebar auf
    function loadWikiEntries() {
      fetch("<?php echo $_SERVER['PHP_SELF']; ?>?action=getWiki")
        .then((response) => response.json())
        .then((data) => {
          const wikiList = document.getElementById("wikiList");
          wikiList.innerHTML = "";
          // Rekursive Funktion zum Rendern der Einträge (mit Einrückung je nach Level)
          function renderEntries(entries, container) {
            entries.forEach((entry) => {
              const li = document.createElement("li");
              li.style.marginLeft =
                (entry.level - 1) * 20 + "px";
              const button = document.createElement("button");
              button.className =
                "w-full text-left font-semibold hover:underline";
              button.textContent = entry.title;
              button.addEventListener("click", () =>
                loadContent(entry)
              );
              li.appendChild(button);
              container.appendChild(li);
              if (entry.children && entry.children.length > 0) {
                renderEntries(entry.children, container);
              }
            });
          }
          renderEntries(data, wikiList);
        })
        .catch((error) =>
          console.error("Fehler beim Laden der Wiki Einträge:", error)
        );
    }

    // Lädt den Inhalt eines Eintrags in den Hauptbereich
    function loadContent(entry) {
      document.getElementById("contentTitle").textContent =
        entry.title;
      document.getElementById("contentArea").textContent =
        entry.content;
    }
  </script>
</body>
</html>
