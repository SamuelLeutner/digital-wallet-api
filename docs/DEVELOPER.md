# Documentação de Desenvolvimento da API

## API Gateway

- **URL:** [`http://localhost:9501/`](http://localhost:9501/)
- Componente central do sistema. Responsável por orquestrar os fluxos, rotear requisições e aplicar testes automatizados
  nos endpoints.

## Banco de Dados (MySQL)

- **URL:** `mysql://localhost:3306`
- **Credenciais:**
    - **Usuário:** `${DB_USERNAME}`
    - **Senha:** `${DB_PASSWORD}`
    - **Database:** `${DB_DATABASE}`

## Visualizador de Banco (Adminer)

- **URL:** [`http://localhost:8080/`](http://localhost:8080/)
- Interface web para consulta e manutenção do banco de dados.

## Cache (Redis)

- **URL:** `redis://localhost:6379`
- **Senha:** `${REDIS_PASSWORD}`
- Utilizado para cache de dados e gerenciamento de sessões.

## RabbitMQ

- **URL:** [`http://localhost:15672/`](http://localhost:15672/)
- **Credenciais:**
    - **Usuário:** `${RABBITMQ_USER}`
    - **Senha:** `${RABBITMQ_PASSWORD}`
- Utilizado para comunicação assíncrona entre os microsserviços através de eventos e filas (mensageria).

## Prometheus

- **URL:** [`http://localhost:9090/`](http://localhost:9090/)
- Responsável pela coleta de métricas e monitoramento da infraestrutura.

## Grafana

- **URL:** [`http://localhost:${GRAFANA_PORT}/`](http://localhost:${GRAFANA_PORT}/)
- **Credenciais:**
    - **Usuário:** `admin`
    - **Senha:** `admin`
- Ferramenta de visualização de métricas e dashboards em tempo real.

---

## Serviços (Docker Compose)

### MySQL

- Porta: `3306`
- Volume: `mysql_data:/var/lib/mysql`

### Redis

- Porta: `6379`
- Volume: `redis_data:/data`

### RabbitMQ

- Portas: `5672` (broker), `15672` (painel)
- Volumes:
    - `rabbitmq_data:/var/lib/rabbitmq`
    - `rabbitmq_logs:/var/log/rabbitmq`

### API Gateway

- Porta: `${API_GATEWAY_PORT}:9501`
- Código-fonte: `./api-gateway:/opt/www`

### Monitoramento

- Prometheus: `9090`
- Grafana: `${GRAFANA_PORT}:3000`
- Provisionamento: `./monitoring/grafana/provisioning`

---

## Referências

- [Documentação Hyperf](https://hyperf.wiki/3.1/#/en/)
- [Introdução ao Prometheus](https://prometheus.io/docs/introduction/overview/)
- [Prometheus via Docker](https://prometheus.io/docs/prometheus/latest/installation/#using-docker)
- [Documentação Grafana](https://grafana.com/docs/)
- [Grafana via Docker](https://grafana.com/docs/grafana/latest/setup-grafana/installation/docker/)
