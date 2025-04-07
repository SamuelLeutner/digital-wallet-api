# Documentação de Desenvolvimento da API

### API Gateway

- **URL:** [`http://localhost:9501/`](http://localhost:9501/)
- Responsável por rotear as requisições para os microserviços.

### Banco de Dados (MySQL)

- **URL:** `mysql://localhost:3306`
- **Credenciais:**
    - **Usuário:** `${DB_USERNAME}`
    - **Senha:** `${DB_PASSWORD}`
    - **Database:** `${DB_DATABASE}`

### Visualizador de Banco de Dados (Adminer)

- **URL:** [`http://localhost:8080/`](http://localhost:8080/)
- Ferramenta para gerenciar o banco de dados via interface web.

### Cache (Redis)

- **URL:** `redis://localhost:6379`
- **Senha:** `${REDIS_PASSWORD}`
- Utilizado para armazenamento em cache e filas de mensagens.

### RabbitMQ

- **URL:** [`http://localhost:15672/`](http://localhost:15672/)
- **Credenciais:**
    - **Usuário:** `${RABBITMQ_USER}`
    - **Senha:** `${RABBITMQ_PASSWORD}`
- Utilizado para gerenciamento de filas e mensagens assíncronas entre serviços.

### Prometheus

- **URL:** [`http://localhost:9090/`](http://localhost:9090/)
- Ferramenta de monitoramento e coleta de métricas.

### Grafana

- **URL:** [`http://localhost:${GRAFANA_PORT}/`](http://localhost:${GRAFANA_PORT}/)
- **Credenciais:**
    - **Usuário:** `admin`
    - **Senha:** `admin`
- Interface para visualização de métricas e dashboards personalizados.

---

## Serviços

### MySQL

- Porta: `3306`
- Persistência de dados: `mysql_data:/var/lib/mysql`

### Redis

- Porta: `6379`
- Persistência de dados: `redis_data:/data`

### RabbitMQ

- Porta: `5672` (mensageria)
- Porta: `15672` (painel de gerenciamento)
- Persistência de dados: `rabbitmq_data:/var/lib/rabbitmq`
- Logs: `rabbitmq_logs:/var/log/rabbitmq`

### API Gateway

- Porta: `${API_GATEWAY_PORT}:9501`
- Mapeado para o diretório: `./api-gateway:/opt/www`

### Monitoramento

- **Prometheus**: `9090`
- **Grafana**: `${GRAFANA_PORT}:3000`
- Configurações de provisionamento: `./monitoring/grafana/provisioning`

---

## Referências

- [Hyperf Documentation](https://hyperf.wiki/3.1/#/en/)
- [Prometheus Overview](https://prometheus.io/docs/introduction/overview/)
- [Instalação do Prometheus via Docker](https://prometheus.io/docs/prometheus/latest/installation/#using-docker)
- [Grafana Documentation](https://grafana.com/docs/)
- [Instalação do Grafana via Docker](https://grafana.com/docs/grafana/latest/setup-grafana/installation/docker/)
