<?php
require_once '../src/auth_guard.php';
require_once '../config/db.php';

// Permissões (mantém-se)

$eventos_json = '[]'; // JSON inicial para o calendário principal
$error_message = null;

try {
    // Ir buscar TODOS os agendamentos para a vista inicial do calendário
    $stmt = $pdo->prepare("
        SELECT a.id, a.titulo, a.data_inicio, a.data_fim, a.tipo_evento, f.nome_completo as funcionario_nome
        FROM agendamentos a JOIN funcionarios f ON a.funcionario_id = f.id ORDER BY a.data_inicio ASC
    ");
    $stmt->execute();
    $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $eventos_para_calendario = [];
    foreach ($agendamentos as $evento) {
        // Formatação dos eventos (igual a antes)
        $eventos_para_calendario[] = [ /* ... (código de formatação igual ao anterior) ... */ ];
    }
    $eventos_json = json_encode($eventos_para_calendario);

} catch (PDOException $e) {
    $error_message = "Erro ao carregar os agendamentos.";
}
?>
<!DOCTYPE html>
<html lang="pt-PT" class="bg-gray-100">
<head>
    <meta charset="UTF-8">
    <title>Calendário - CrewSync</title>
    <link href="<?= BASE_URL ?>/public/css/style.css" rel="stylesheet">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js'></script>
    <style>
        /* Container principal do calendário */
        #calendar {
            max-width: 1100px; /* Ou ajuste conforme preferir */
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            font-size: 14px; /* Tamanho base da fonte para o calendário */
        }
        /* Estilo geral para os eventos */
        .fc-event {
             border: 1px solid rgba(0,0,0,0.1) !important; /* Borda subtil */
             padding: 3px 5px; /* Espaçamento interno */
             font-size: 0.8em; /* Fonte um pouco menor */
             cursor: default; /* Cursor normal, já que não são clicáveis por agora */
             /* Usar as cores de fundo/texto definidas no PHP */
             color: var(--fc-event-text-color, #1f2937) !important; /* Cor do texto (com fallback) */
             background-color: var(--fc-event-bg-color, #93c5fd) !important; /* Cor de fundo */
        }
        /* Especificamente para eventos na vista mensal (dayGrid) */
        .fc-daygrid-event {
            margin-top: 2px;
            margin-bottom: 2px;
        }
        /* Permitir quebra de linha no título do evento na vista mensal */
        .fc-daygrid-event .fc-event-title {
            white-space: normal !important; 
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2; /* Limitar a 2 linhas, por exemplo */
            line-clamp: 2; /* Propriedade padrão para compatibilidade */
            -webkit-box-orient: vertical;
        }
        /* Estilos para o Modal (se já tiver implementado) */
        #modal-daily-details ul { list-style: none; padding: 0; }
        #modal-daily-details li { margin-bottom: 8px; font-size: 0.9em; }
        #modal-daily-details li span.font-medium { color: #333; }
        #modal-daily-details li span.text-xs { color: #666; }

        /* Ajustes de cores padrão do FullCalendar (Opcional) */
        :root {
            --fc-border-color: #e5e7eb; /* cinza-200 */
            --fc-daygrid-day-bg-color: transparent;
            --fc-today-bg-color: rgba(253, 230, 138, 0.2); /* amarelo-200 com transparência */
        }
        .fc .fc-toolbar-title {
            font-size: 1.5em; /* Aumentar o título do mês */
            color: #374151; /* cinza-700 */
        }
        .fc .fc-button-primary {
            background-color: #3b82f6; /* azul-500 */
            border-color: #3b82f6;
        }
        .fc .fc-button-primary:hover {
            background-color: #2563eb; /* azul-600 */
            border-color: #2563eb;
        }
        .fc .fc-daygrid-day-number {
             padding: 0.5em;
             color: #4b5563; /* cinza-600 */
        }
    </style>
</head>
<body class="bg-gray-100">

    <?php include_once '../src/templates/header.php'; ?>

    <main class="p-8">
        <div class="max-w-7xl mx-auto">
             <div class="flex justify-between items-center mb-6">
                 <h1 class="text-3xl font-bold text-gray-800">Calendário Geral</h1>
                 <a href="adicionar_agendamento.php" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg hover:bg-blue-700">
                     + Novo Agendamento
                 </a>
            </div>

            <?php if (isset($error_message)): ?>
                <?php endif; ?>

            <div id='calendar'></div>

            
        </div>
        <div id="modal-daily-details" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div id="modal-overlay" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    Eventos para [Data]
                                </h3>
                                <div class="mt-4">
                                    <ul id="modal-details-list" class="space-y-2 max-h-60 overflow-y-auto pr-2">
                                        <li class="italic text-gray-500">A carregar...</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" id="modal-close-btn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Fechar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        
        // --- Referências para os elementos do MODAL ---
        var modal = document.getElementById('modal-daily-details');
        var modalOverlay = document.getElementById('modal-overlay');
        var modalTitle = document.getElementById('modal-title');
        var modalList = document.getElementById('modal-details-list');
        var modalCloseBtn = document.getElementById('modal-close-btn');

        var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'pt',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: <?= $eventos_json ?? '[]' ?>, // Usar '[]' como fallback seguro
        selectable: true,

        dateClick: function(info) {
            const clickedDate = info.dateStr;
            const formattedDate = new Date(clickedDate + 'T00:00:00').toLocaleDateString('pt-PT', { day: 'numeric', month: 'long', year: 'numeric' });
            
            // --- Atualizar e mostrar o MODAL ---
            modalTitle.textContent = 'Eventos para ' + formattedDate;
            modalList.innerHTML = '<li class="italic text-gray-500">A carregar...</li>'; // Feedback
            modal.classList.remove('hidden'); // Mostrar o modal

            // Fazer o pedido Fetch (igual a antes)
            fetch('get_eventos_dia.php?data=' + clickedDate)
            .then(response => {
                if (!response.ok) { throw new Error('Network response was not ok'); }
                return response.json();
            })
            .then(eventosDoDia => {
                modalList.innerHTML = ''; // Limpar a lista
                if (eventosDoDia.length === 0) {
                modalList.innerHTML = '<li class="italic text-gray-500">Nenhum evento encontrado para este dia.</li>';
                } else {
                eventosDoDia.forEach(evento => {
                    const li = document.createElement('li');
                    let borderColorClass = 'border-l-4 border-blue-400';
                    if (evento.tipo_evento?.toLowerCase() === 'folga') borderColorClass = 'border-l-4 border-orange-400';
                    if (evento.tipo_evento?.toLowerCase() === 'médico') borderColorClass = 'border-l-4 border-red-400';
                    
                    li.className = 'bg-white p-2 rounded shadow-sm text-sm ' + borderColorClass;
                    li.innerHTML = `
                    <span class="font-medium">${evento.titulo || 'Evento sem título'}</span> - ${evento.funcionario_nome || 'Funcionário Desconhecido'} 
                    <span class="text-xs text-gray-500">(${evento.tipo_evento || 'Geral'})</span>`;
                    modalList.appendChild(li);
                });
                }
            })
            .catch(error => {
                console.error('Erro ao buscar eventos:', error);
                modalList.innerHTML = '<li class="text-red-600">Erro ao carregar eventos. Tente novamente.</li>';
            });
        }, // Fim do dateClick

        eventDidMount: function(info) {
            // Adicionar tooltip simples (opcional)
            info.el.setAttribute('title', info.event.title);
        }

        }); // Fim da inicialização do Calendar

        // --- Funções para fechar o modal ---
        function closeModal() {
            modal.classList.add('hidden');
        }
        modalCloseBtn.addEventListener('click', closeModal);
        modalOverlay.addEventListener('click', closeModal); // Fechar ao clicar fora
        
        // Fechar com a tecla Escape
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });

        calendar.render();
    }); // Fim do DOMContentLoaded
    </script>
</body>
</html>