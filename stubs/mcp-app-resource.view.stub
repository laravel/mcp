<x-mcp::app :title="$title">
    <x-slot:head>
        <script type="module">
        createMcpApp(async (app) => {
            document.getElementById('run-btn').addEventListener('click', async () => {
                const result = await app.callServerTool('tool-name', {});
                const output = document.getElementById('output');

                if (result.isError) {
                    output.textContent = `Error: ${result.content[0]?.text ?? 'Unknown error'}`;
                    return;
                }

                output.textContent = result.content[0]?.text ?? '';
            });
        });
        </script>
    </x-slot:head>

    <div id="app">
        <button id="run-btn">Run</button>
        <p id="output"></p>
    </div>
</x-mcp::app>
