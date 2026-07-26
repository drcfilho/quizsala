# QuizSala — Proposta de Melhorias Futuras

Documento de mapeamento de funcionalidades e evoluções planejadas para o **QuizSala**, respeitando a filosofia do produto (sem gamificação estilo Kahoot, foco em diagnóstico honesto em sala de aula offline, 100% web local e estética de Cartão-Resposta).

---

## 1. 📊 Relatórios & Exportação (Pós-Aula)

- **Exportação para Diário de Classe (CSV / Excel / PDF):**
  Gerar um relatório completo pós-sessão com a lista de alunos (quando no modo nomeado), quantidade de acertos, nota percentual e tempo de conclusão. Permite ao professor importar ou digitar notas no diário oficial da escola sem trabalho manual.

- **Relatório de Diagnóstico da Turma (Mapa de Calor):**
  Identificação automática de questões em que mais de 50% da turma errou. O relatório gera um resumo pedagógico para o professor saber exatamente quais tópicos precisam ser revisados antes da próxima avaliação.

---

## 2. 📝 Gestão de Banco de Questões

- **Embaralhamento de Alternativas por Aluno (Anti-Cola Sutil):**
  Para cada aluno que entra na sessão, a ordem visual das alternativas (A, B, C, D) é apresentada de forma aleatória, enquanto o servidor mantém o gabarito original. Previne cópia visual entre carteiras vizinhas sem adicionar contadores de velocidade ou elementos de competição.

- **Etiquetas / Tags de Conteúdo:**
  Classificação das questões do banco de dados com marcas/tags por disciplina, tópico ou nível de dificuldade (ex.: `#redes`, `#hardware`, `#facil`). Facilita a busca e permite selecionar N questões por tag para gerar provas rapidamente.

---

## 3. 📶 Resiliência de Rede & Infraestrutura Offline

- **Modo PWA (Progressive Web App):**
  Inclusão de `manifest.json` e Service Worker básico para armazenamento em cache de assets (`bulma.css`, fontes, JS). Permite instalar o aplicativo na tela inicial do celular do aluno e reduz em até 80% as requisições estáticas para o roteador Wi-Fi da sala de aula.

- **Medidor de Saúde da Rede no Celular do Professor:**
  Indicador de latência (ping) e taxa de resposta do poll no painel de controle do professor ([sessao.php](file:///C:/Users/drcfi/Documents/ClaudeP/Quizsala.app/public/admin/sessao.php)), alertando caso o roteador local esteja com sobrecarga ou interferência de sinal.

---

## 4. 🖨️ Avaliação Híbrida (Papel & Celular)

- **Gerador de Prova em PDF para Impressão:**
  Exportação da prova cadastrada em formato PDF pronto para impressão física (layout impresso oficial de 2 colunas com bolhas de leitura óptica). Garante a aplicação do mesmo exame em turmas onde nem todos os alunos possuem dispositivo móvel disponível no dia.
