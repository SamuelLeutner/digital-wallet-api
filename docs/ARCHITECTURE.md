

# Documentação da Arquitetura da API

## 1. Visão Geral
Esta API foi projetada para gerenciar transações financeiras de forma eficiente e escalável, utilizando uma arquitetura baseada em microsserviços e eventos (EDA). Possibilitando a segregação das responsabilidades segundo o padrão (CQRS). O objetivo é garantir segurança, rastreabilidade e comunicação assíncrona entre os serviços.

## 2. UML da Arquitetura
![UML digital-wallet-api](https://www.mermaidchart.com/raw/6b2a1b6d-3931-4f95-9823-2216c04d767b?theme=light&version=v0.1&format=svg)
[Link para o diagrama](https://www.mermaidchart.com/raw/6b2a1b6d-3931-4f95-9823-2216c04d767b?theme=light&version=v0.1&format=svg)

## 3. Componentes Principais

### 3.1 API Gateway

**Tecnologia**: Hyperf (PHP)

**Responsabilidades**:
- Receber e validar requisições HTTP/REST
- Roteamento inteligente para microsserviços
- Balanceamento de carga básico
- Gerenciamento de circuit breaker para serviços externos
- Coleta de métricas básicas de performance

### 3.2 Wallet Service

**Tecnologia**: Golang

**Responsabilidades**:
- Gestão de carteiras digitais
- Processamento transacional financeiro
- Validação de regras de negócio:
  - Restrição para lojistas (apenas recebem)
  - Verificação de saldo disponível
  - Limites de transferência
- Garantia ACID em operações
- Integração com serviços externos de autorização

### 3.3 Socket Service

**Tecnologia**: Node.js com Socket.IO

**Responsabilidades**:
- Manutenção de conexões persistentes WebSocket
- Notificações em tempo real para clientes
- Gerenciamento de estado de conexões
- Broadcast de atualizações de transações
- Controle de sessões ativas

### 3.4 Authorization API (Serviço Externo)

**Integração**:
- Endpoint: `GET /authorize`
- Protocolo: REST/JSON
- Circuit breaker: 3 falhas consecutivas abrem o circuito

### 3.5 Notification API (Serviço Externo)

**Integração**:
- Endpoint: `POST /notify`
- Protocolo: REST/JSON
- Política de retentativas: 3 tentativas com backoff exponencial
- DLQ (Dead Letter Queue) para falhas persistentes

### 3.6 Infraestrutura de Dados

**MySQL**:
- Esquema principal:
  - `wallets` (saldo, tipo de usuário)
  - `transactions` (histórico completo)
- Configuração:
  - Isolation level: READ COMMITTED
  - Índices otimizados para consultas por usuário

**Redis**:
- Uso principal:
  - Armazenamento de estado de conexões WebSocket
  - Cache de consultas frequentes
  - Pub/Sub para notificações
- TTL padrão: 24 horas para sessões

**RabbitMQ**:
- Exchange: `transactions.direct`
- Filas principais:
  - `transfer.queue` (processamento principal)
  - `transfer.dlq` (mensagens problemáticas)
- Políticas:
  - Retentativa após falha (3x)
  - Persistência de mensagens

