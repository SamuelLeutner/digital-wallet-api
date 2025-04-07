# Digital Wallet API

## Tecnologias Utilizadas

Este projeto utiliza uma arquitetura baseada em microsserviços, garantindo escalabilidade e comunicação assíncrona. As
principais tecnologias empregadas incluem:

- **Hyperf (PHP)**: API Gateway para gerenciamento de requisições e roteamento inteligente.
- **Node.js + Socket.IO**: Serviço WebSocket para notificações em tempo real.
- **MySQL**: Banco de dados relacional para garantir consistência transacional.
- **Redis**: Cache e gerenciamento de sessões.
- **RabbitMQ**: Mensageria para orquestração assíncrona dos eventos.
- **Prometheus & Grafana**: Stack de observabilidade para métricas e logs.

## Arquitetura

A API segue um modelo baseado em eventos (EDA), garantindo alta disponibilidade e consistência eventual entre os
serviços.

### Documentações

- [Documentação da Arquitetura da API](docs/ARCHITECTURE.md)
- [Documentação de Desenvoldimento da API](docs/DEVELOPER.md)

## Pré-requisitos

- Docker

## Instalação

1. Clone o repositório:

```bash
  git clone https://github.com/SamuelLeutner/digital-wallet-api.git
```

Acesse o repositório

```bash
  cd digital-wallet-api
```

Copie as .env

```bash
  cp .env-example .env
```

2. Buildar o Projeto

```bash
  docker compose build
```

3. Startar o projeto

```bash
  docker compose up -d
```

4. Rodar as migrations e seeders

```bash
  docker compose exec api_gateway php bin/hyperf.php migrate --seed
```

5. Para verificar a qualidade código

```bash
    docker-compose run --rm phpqa phpmd app text phpmd.xml | grep -v 'Deprecated'
```

6. Para rodar os testes

```bash
  docker compose exec api_gateway composer test
```
