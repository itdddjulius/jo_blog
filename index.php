<?php
// =============================
// CONFIG
// =============================
date_default_timezone_set('Europe/London');

$pagesDir = __DIR__ . '/PAGES';

// Create directory if missing
if (!is_dir($pagesDir)) {
    mkdir($pagesDir, 0777, true);
}

// =============================
// SAVE HANDLER
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'save') {

    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['content'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
        exit;
    }

    $content = $input['content'];

    // If editing existing file
    if (!empty($input['file'])) {
        $safeFile = basename($input['file']); // Prevent path traversal
        $filename = $pagesDir . '/' . $safeFile;
    } else {
        // Create new timestamp file
        $filename = $pagesDir . '/' . date('Y-m-d_His') . '.html';
    }

    file_put_contents($filename, $content);

    echo json_encode(['status' => 'success']);
    exit;
}

// =============================
// LOAD ALL .html FILES
// =============================
$files = glob($pagesDir . '/*.html');
$posts = [];

foreach ($files as $file) {
    $posts[] = [
        'file' => basename($file),
        'content' => file_get_contents($file),
        'mtime' => filemtime($file)
    ];
}

// Sort newest first
usort($posts, function($a, $b) {
    return $b['mtime'] <=> $a['mtime'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>The Black Times</title>

<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
body { background:#000; color:#fff; font-family:Georgia, serif; }
.newspaper-cols { column-count:2; column-gap:3rem; column-rule:1px solid #222; }
.horizontal-scroll { scroll-snap-type:x mandatory; scroll-behavior:smooth; }
.page-card { scroll-snap-align:start; flex:0 0 100%; }
::-webkit-scrollbar { height:8px; }
::-webkit-scrollbar-thumb { background:#22c55e; }
.prose-custom img { border:1px solid #333; margin:1rem 0; width:100%; filter:grayscale(100%); }
.prose-custom h1 { font-size:3rem; font-weight:900; text-transform:uppercase; margin-bottom:1rem; }
</style>
</head>

<body x-data="blogApp()" class="h-screen flex flex-col overflow-hidden">

<header class="border-b-4 border-white p-4 flex justify-between items-center">
    <h1 class="text-3xl font-black uppercase italic">The Black Times</h1>
    <button @click="openEditor()" class="bg-green-600 text-black font-bold px-6 py-2 text-xs uppercase">
        <i class="fas fa-plus mr-2"></i> New Entry
    </button>
</header>

<main class="flex-1 relative flex flex-col overflow-hidden">

    <button @click="scrollPrev()" class="absolute left-4 bottom-12 z-40 bg-green-600 text-black w-12 h-12 rounded-full">
        <i class="fas fa-chevron-left"></i>
    </button>

    <button @click="scrollNext()" class="absolute right-4 bottom-12 z-40 bg-green-600 text-black w-12 h-12 rounded-full">
        <i class="fas fa-chevron-right"></i>
    </button>

    <div id="scroller" class="flex-1 overflow-x-auto flex horizontal-scroll">

        <template x-for="(post,index) in posts" :key="post.file">
            <article class="page-card p-12 border-r border-zinc-800 flex flex-col">
                <div class="max-w-6xl mx-auto flex-1 overflow-y-auto pr-4 w-full">

                    <div class="flex justify-between border-b border-zinc-700 mb-6 pb-2 text-xs uppercase text-zinc-500">
                        <span x-text="'File: ' + post.file"></span>
                        <span x-text="index === 0 ? 'Breaking News' : 'Snippet ' + (index+1)"></span>
                    </div>

                    <div class="prose-custom text-zinc-300 leading-relaxed text-justify newspaper-cols"
                         x-html="post.content"></div>
                </div>

                <div class="pt-4 border-t border-zinc-900 flex justify-end">
                    <button @click="openEditor(index)"
                            class="border border-green-500 text-green-500 px-6 py-2 uppercase text-xs">
                        <i class="fas fa-edit mr-2"></i> Edit File
                    </button>
                </div>
            </article>
        </template>

    </div>
</main>

<footer class="bg-zinc-900 p-3 text-center text-xs uppercase text-zinc-400">
    Another website by Julius Olatokunbo
</footer>

<!-- EDITOR MODAL -->
<div x-show="showEditor" class="fixed inset-0 bg-black/95 z-50 flex items-center justify-center p-8">
    <div class="bg-zinc-900 border border-zinc-700 w-full max-w-5xl h-[80vh] flex flex-col rounded-lg">

        <div class="p-4 border-b border-zinc-800 flex justify-between">
            <div>
                <button @click="editMode=false"
                        :class="!editMode ? 'bg-green-600 text-black':'bg-zinc-800'"
                        class="px-4 py-1 text-xs font-bold">RENDER</button>

                <button @click="editMode=true"
                        :class="editMode ? 'bg-green-600 text-black':'bg-zinc-800'"
                        class="px-4 py-1 text-xs font-bold">EDIT</button>
            </div>
            <button @click="showEditor=false"><i class="fas fa-times"></i></button>
        </div>

        <div class="flex-1 overflow-hidden">
            <textarea x-show="editMode"
                      x-model="tempContent"
                      class="w-full h-full bg-black text-green-400 font-mono p-6"></textarea>

            <div x-show="!editMode"
                 class="w-full h-full p-10 overflow-y-auto bg-white text-black prose-custom"
                 x-html="tempContent"></div>
        </div>

        <div class="p-4 border-t border-zinc-800 flex justify-end">
            <button @click="saveToServer"
                    class="bg-green-600 text-black font-bold px-8 py-2 uppercase text-sm">
                Overwrite Physical File
            </button>
        </div>
    </div>
</div>

<script>
function blogApp(){
    return{
        posts: <?php echo json_encode($posts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>,
        showEditor:false,
        editMode:true,
        editingIndex:null,
        tempContent:'',

        openEditor(index=null){
            this.editingIndex=index;
            this.tempContent=index!==null
                ? this.posts[index].content
                : '<h1>New Story</h1><p>Edit here...</p>';
            this.showEditor=true;
        },

        async saveToServer(){
            let payload={
                content:this.tempContent
            };

            if(this.editingIndex!==null){
                payload.file=this.posts[this.editingIndex].file;
            }

            await fetch('?action=save',{
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body:JSON.stringify(payload)
            });

            window.location.reload();
        },

        scrollNext(){
            document.getElementById('scroller')
                .scrollBy({left:window.innerWidth,behavior:'smooth'});
        },

        scrollPrev(){
            document.getElementById('scroller')
                .scrollBy({left:-window.innerWidth,behavior:'smooth'});
        }
    }
}
</script>

</body>
</html>
