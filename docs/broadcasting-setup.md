# Sistema de Broadcasting em Tempo Real

Este documento explica como funciona o sistema de broadcasting implementado para atualizações de status de pedidos em tempo real.

## Visão Geral

O sistema permite que quando um usuário atualiza o status de um pedido (separado/embalado/coletado), todos os outros usuários que estão na tela de pedidos vejam a atualização instantaneamente, sem precisar recarregar a página.

## Componentes Implementados

### Backend (Laravel)

1. **Evento de Broadcasting**: `App\Events\OrderStatusUpdated`
   - Dispara quando o status de um pedido é atualizado
   - Contém informações sobre o pedido, usuário, ação e novo status

2. **Canais de Broadcasting**:
   - `orders` - Canal público para todas as atualizações de pedidos
   - `orders.{orderId}` - Canal específico para um pedido

3. **Configuração**: 
   - Usa Laravel Reverb como servidor WebSocket
   - Configurado no arquivo `config/broadcasting.php`

### Frontend (Vue.js)

1. **Composable**: `useOrderBroadcasting`
   - Gerencia a conexão com os canais de broadcasting
   - Escuta atualizações e atualiza o estado local

2. **Integração na Página de Pedidos**:
   - Conecta automaticamente ao canal `orders`
   - Atualiza a interface em tempo real quando recebe eventos
   - Mostra indicador visual de conexão

## Como Funciona

1. **Usuário A** atualiza o status de um pedido
2. **Laravel** dispara o evento `OrderStatusUpdated`
3. **Reverb** envia o evento via WebSocket para todos os clientes conectados
4. **Vue.js** recebe o evento e atualiza a interface local
5. **Todos os outros usuários** veem a atualização instantaneamente

## Configuração

### Variáveis de Ambiente

```env
# Broadcasting Configuration
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=local
REVERB_APP_KEY=local
REVERB_APP_SECRET=local
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

# Frontend Broadcasting
VITE_PUSHER_APP_KEY=local
VITE_PUSHER_HOST=127.0.0.1
VITE_PUSHER_PORT=8080
VITE_PUSHER_SCHEME=http
```

### Iniciando o Servidor

Para que o broadcasting funcione, você precisa iniciar o servidor Reverb:

```bash
php artisan reverb:start
```

### Comando de Desenvolvimento

Use o comando `composer run dev` que já está configurado para iniciar todos os serviços necessários:

```bash
composer run dev
```

Este comando inicia:
- Servidor Laravel
- Queue worker
- Pail (logs)
- Vite (frontend)
- **Reverb (broadcasting)**

## Testando o Sistema

### 1. Comando de Teste

Use o comando Artisan para testar o broadcasting:

```bash
php artisan broadcast:test {order_id} {action} {status}
```

Exemplos:
```bash
php artisan broadcast:test 1 picking true
php artisan broadcast:test 1 packing false
php artisan broadcast:test 1 picked_up true
```

### 2. Teste Manual

1. Abra duas abas do navegador na página de pedidos
2. Faça login com usuários diferentes (ou a mesma conta)
3. Em uma aba, atualize o status de um pedido
4. Na outra aba, você deve ver a atualização instantaneamente

## Indicadores Visuais

- **Ponto Verde**: "Tempo real ativo" - Conexão estabelecida
- **Ponto Vermelho**: "Sem conexão" - Problema na conexão WebSocket

## Troubleshooting

### Problema: Não vejo atualizações em tempo real

1. Verifique se o Reverb está rodando:
   ```bash
   php artisan reverb:start
   ```

2. Verifique o console do navegador para erros de WebSocket

3. Verifique se as variáveis de ambiente estão corretas

### Problema: Erro de conexão WebSocket

1. Verifique se a porta 8080 está disponível
2. Verifique se não há firewall bloqueando a conexão
3. Tente reiniciar o servidor Reverb

### Problema: Eventos não são enviados

1. Verifique se o broadcasting está habilitado no `.env`
2. Verifique se o evento está sendo disparado nos controladores
3. Verifique os logs do Laravel para erros

## Estrutura dos Dados

### Evento OrderStatusUpdated

```json
{
  "order": {
    "id": 1,
    "numero": "12345",
    "contato_nome": "Cliente Exemplo"
  },
  "user": {
    "id": 1,
    "name": "João Silva"
  },
  "action": "picking",
  "action_name": "Separado",
  "status": true,
  "timestamp": "2024-01-15T10:30:00.000Z"
}
```

## Próximos Passos

- [ ] Implementar notificações toast para melhor UX
- [ ] Adicionar broadcasting para outras páginas (Kanban, Labels)
- [ ] Implementar presença de usuários online
- [ ] Adicionar logs de auditoria para eventos de broadcasting
