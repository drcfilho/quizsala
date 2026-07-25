# QuizSala — Setup

Guia pra rodar o QuizSala num notebook, do zero, sem depender de internet na hora da aula.

## O que precisa estar instalado

No Windows, **nada precisa estar pré-instalado** — `iniciar.bat` resolve sozinho (ver seção abaixo). Em outros sistemas, ou se preferir instalar você mesmo:

- **PHP 8.2 ou mais novo**, com a extensão `pdo_sqlite` habilitada (vem habilitada por padrão na maioria das instalações — confira com `php -m` e procure `pdo_sqlite` na lista).
  - Windows: [windows.php.net/download](https://windows.php.net/download/) — baixe o zip "Non Thread Safe", extraia numa pasta como `C:\php`, e adicione essa pasta ao `PATH` do sistema. Também precisa do **Visual C++ Redistributable** ([link direto](https://aka.ms/vs/17/release/vc_redist.x64.exe)) instalado — sem ele o `php.exe` do Windows não roda.
  - Depois de instalar, **abra um terminal novo** (o PATH só atualiza em janelas novas) e confirme com `php -v`.
- Nenhum banco de dados separado, nenhum servidor web (Apache/Nginx), nenhum `npm install`. O PHP embutido (`php -S`) e o SQLite (arquivo único) são o suficiente — é por isso que o projeto roda num notebook que troca de sala sem setup pesado (`arquitetura.md` D1).

## Rodando pela primeira vez

**Opção 1 — duplo clique (Windows), o jeito recomendado:** dê duplo clique em `iniciar.bat`.

Ele confere sozinho se o sistema já tem PHP e Visual C++ Redistributable:

- **Tem os dois?** Sobe o servidor direto, cria o banco na primeira vez e abre o projetor no navegador.
- **Falta algo?** Mostra um menu:
  1. Instalar via `winget` (recomendado, precisa de internet)
  2. Instalar só o Visual C++ Redistributable, com o instalador já embutido em `utils\vc_redist.x64.exe` (não precisa de internet)
  3. Usar a cópia portátil de PHP em `php\` (não instala nada no sistema, nem precisa de internet)
  4. Sair e instalar manualmente (instruções acima)

Ou seja: num notebook novo, sem internet na hora, a opção 3 ainda funciona — é por isso que o release traz `php\` e `utils\` prontos.

**Opção 2 — manual, qualquer sistema:**
```sh
php bin/init-db.php        # cria o banco com uma prova de exemplo e a sala AULA01
cd public
php -S 0.0.0.0:8080        # 0.0.0.0, não 127.0.0.1 - senão os celulares nao alcancam
```
Depois abra `http://localhost:8080/tela.php` (projetor — sem código na URL, ele acha sozinho a sessão ativa mais recente) ou `http://localhost:8080/admin/index.php` (admin).

`iniciar.ps1` é o script de verdade (detecção de PHP, versão, IP de rede, abrir o navegador) — `iniciar.bat` só chama ele. Se preferir rodar via PowerShell diretamente: botão direito em `iniciar.ps1` → "Executar com PowerShell".

## Montando a rede da sala

1. **Roteador**: SSID aberto (sem senha), DHCP ligado. Rede fechada, sem internet — não precisa e não deve ter senha de Wi-Fi (menos fricção pros alunos entrarem).
2. **IP fixo no notebook**: configure um IP estático na interface Wi-Fi/Ethernet que os celulares vão usar (ex.: `192.168.0.10`), pra não mudar entre reinícios. Os scripts de partida tentam detectar o IP automaticamente, mas confira se bateu com o que está esperando antes de projetar.
3. **Firewall do Windows — porta 8080**: é aqui que trava na primeira vez, e o sintoma engana — funciona perfeitamente em `localhost` no próprio notebook e **nenhum celular consegue entrar**. Libere a porta:
   ```powershell
   New-NetFirewallRule -DisplayName "QuizSala" -Direction Inbound -LocalPort 8080 -Protocol TCP -Action Allow
   ```
   (Rode como Administrador uma vez só; a regra fica salva.)
4. **Desative a suspensão automática** do notebook (Configurações → Energia) — se a tela apagar e o PC suspender no meio da aula, o servidor cai e os celulares perdem a conexão.

## Checklist de aula

- [ ] Notebook com bateria/carregador, sem suspensão automática
- [ ] Roteador ligado, SSID aberto visível
- [ ] `iniciar.bat` (ou `php bin/init-db.php` + servidor manual) rodando, sem erro no console
- [ ] IP mostrado no console bate com a rede do roteador (não é `169.254.x.x` nem um adaptador virtual)
- [ ] Projetor conectado e mostrando a tela (`tela.php`)
- [ ] Testar em UM celular antes da turma inteira: entrar, responder, ver revelação
- [ ] Prova que vai ser aplicada está **publicada** (`admin/provas.php` → "Publicar") — sem isso ela não aparece pra abrir sessão

## Depois da aula

Nenhum passo obrigatório — feche a janela do console pra encerrar o servidor. O banco (`db/quizsala.sqlite`) fica no disco entre uma aula e outra; use "Encerrar e limpar sessão" no admin quando quiser zerar participantes/respostas de uma sessão específica, mantendo as provas.
