# Arquitetura Base para Plataforma de Transferências

---

#### Contexto

Este projeto propõe uma arquitetura voltada à construção de uma plataforma de transferências entre usuários, com lógica
de negócio rigorosa, integração com serviços externos e resiliência a falhas. O desafio exige consistência, boa
separação de responsabilidades e testes automatizados confiáveis.

---

### Diagrama UML da Arquitetura

Abaixo está o diagrama que representa a visão de alto nível da arquitetura da plataforma de transferências, com foco no
fluxo de transferência, comunicação assíncrona via fila e integração com serviços externos.

![UML da arquitetura](project-architecture.png)

---

#### Decisão

Foi adotada uma **arquitetura modular baseada em Hyperf**, com foco principal no `api-gateway` como **núcleo central do
sistema**.

---

#### API Gateway como Core da Solução

O componente `api-gateway` concentra:

- Toda a **lógica de orquestração** da operação de transferência;
- Validações de negócio (como tipo de usuário, saldo e restrições);
- Execução e controle da **transação inicial no banco**;
- Emissão de mensagens para filas que acionam etapas assíncronas da saga;
- Retorno imediato para o cliente com status da requisição;
- Implementação dos **testes de unidade e integração**;
- Simulações dos serviços externos com testes controlados;
- Registro e controle das etapas da saga de transferência.

A decisão de centralizar o domínio no `api-gateway` garante **testabilidade, rastreabilidade e controle transacional**
completo da aplicação.

---

#### Componentes da Arquitetura

##### `api-gateway` (Hyperf)

- Entry point RESTful para o sistema;
- Camada principal de orquestração de negócio;
- Persistência das transações e etapas da saga;
- Geração de eventos para mensageria (transferência, compensação e notificação);
- Core dos testes automatizados.

##### Banco de Dados (MySQL)

- Estrutura relacional para usuários, carteiras, transações, etapas da saga e notificações;
- Suporte a transações ACID.

##### RabbitMQ – Mensageria

Foi adotado o **modelo de tópicos (topic exchange)** com separação por contexto:

| Exchange           | Routing Key        | Producer                 | Propósito                                  |
|--------------------|--------------------|--------------------------|--------------------------------------------|
| `transfers`        | `transfers.create` | `TransfersProducer`      | Inicia o fluxo de transferência            |
| `saga`             | `saga.compensate`  | `SagaCompensateProducer` | Gerencia compensações                      |
| `ws_notifications` | `ws.notify`        | `NotificationsProducer`  | Dispara notificações internas persistentes |

Essa separação facilita manutenção, escalabilidade por domínio e leitura semântica clara da fila.

##### Serviços Externos

| Serviço     | URL Mock                                   | Método | Descrição                                |
|-------------|--------------------------------------------|--------|------------------------------------------|
| Autorizador | `https://util.devi.tools/api/v2/authorize` | `GET`  | Verifica se a transferência é autorizada |
| Notificação | `https://util.devi.tools/api/v1/notify`    | `POST` | Dispara notificação para o recebedor     |

---

#### Justificativas

- O `api-gateway` concentra o domínio, facilitando manutenção e testes;
- Saga orquestrada permite consistência mesmo em falhas parciais;
- RabbitMQ fornece desacoplamento e resiliência entre as etapas;
- Exchanges nomeadas por contexto aumentam a clareza e manutenibilidade;
- Circuit breakers (planejados) protegem contra instabilidade externa.

---

#### Consequências

- Mensageria torna o sistema tolerante a falhas de curto prazo;
- O core de domínio bem definido facilita a futura extração de serviços independentes;
- Alto grau de desacoplamento entre API, domínio e serviços externos;
- A orquestração no `api-gateway` oferece um ponto de entrada único e rastreável;
- Sistema preparado para escalar horizontalmente os consumidores e produtores.

---

#### Próximos Passos

1. **Implementação de Circuit Breaker**

- Proteger chamadas aos serviços externos (Autorizador e Notificação) para evitar sobrecarga ou espera desnecessária
  durante instabilidades.

2. **Divisão por Escopos (Extração Gradual de Microsserviços)**

- Separar os contextos de negócio em serviços independentes:
    - Serviço de Carteira (Wallet Service)
    - Serviço de Transferência (Transfer Service)
    - Serviço de Notificação (Notification Service)
- Permitir deploy, escala e versionamento independentes.
- Utilizar mensageria e REST para comunicação entre serviços.

3. **Observabilidade**

- Adição de logs estruturados, rastreamento distribuído (trace IDs) e métricas.
- Preparar para integração com ferramentas como Grafana, Prometheus, OpenTelemetry.

4. **CLI/Admin para Recuperação de Transações**

- Permitir reprocessamento de transações falhadas ou pendentes via terminal.

5. **Melhorias na Cobertura de Testes**

- Mais casos de testes para falhas de autorização, inconsistências e compensações.

---

#### Conclusão

A arquitetura proposta, com o `api-gateway` como **núcleo de orquestração**, é sólida e expansível. Ela proporciona um
ambiente seguro para evoluir gradualmente em direção a microsserviços, manter o domínio centralizado, garantir
consistência por meio de sagas e tolerar falhas externas com um plano claro de circuit breaker.
