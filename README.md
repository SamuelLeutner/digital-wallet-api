# Digital Wallet API

## Tecnologias Utilizadas
Este projeto utiliza uma arquitetura baseada em microsserviços, garantindo escalabilidade e comunicação assíncrona. As principais tecnologias empregadas incluem:

- **Hyperf (PHP)**: API Gateway para gerenciamento de requisições e roteamento inteligente.
- **Golang**: Serviço de carteira digital para processamento de transações financeiras.
- **Node.js + Socket.IO**: Serviço WebSocket para notificações em tempo real.
- **MySQL**: Banco de dados relacional para garantir consistência transacional.
- **Redis**: Cache e gerenciamento de sessões.
- **RabbitMQ**: Mensageria para orquestração assíncrona dos eventos.
- **Prometheus & Grafana**: Stack de observabilidade para métricas e logs.

## Arquitetura
A API segue um modelo baseado em eventos (EDA), garantindo alta disponibilidade e consistência eventual entre os serviços.

### Documentações
[Documentação da Arquitetura da API](docs/ARCHITECTURE.md)
[Documentação de Desenvoldimento da API](docs/DEVELOPER.md)
