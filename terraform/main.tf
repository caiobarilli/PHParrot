locals {
  project_path = coalesce(var.project_path, abspath("${path.module}/.."))
  prod         = var.ambiente == "prod"

  log_rotativo = {
    "max-size" = "10m"
    "max-file" = "3"
  }

  ia = !local.prod && var.gateway_token_arquivo != null

  gateway_contexto = abspath("${path.module}/../docker/gateway")
  gateway_arquivos = [
    for arquivo in fileset(local.gateway_contexto, "**") : arquivo
    if !can(regex("(^|/)(__pycache__|\\.venv|\\.mypy_cache|\\.ruff_cache|\\.pytest_cache)(/|$)", arquivo))
  ]
  gateway_token = "/run/secrets/phparrot-gateway-token"

  whisper_sha1 = {
    base  = "465707469ff3a37a2b9b8d8f89f2f99de7299dac"
    small = "55356645c2b361a969dfd0ef2c5a50d530afd8d5"
  }
}

resource "docker_network" "phparrot_network" {
  name = "phparrot-network"
}

resource "docker_image" "php" {
  name = "phparrot-php"

  triggers = {
    dockerfile = filesha1("${path.module}/../docker/php/Dockerfile")
    php_ini    = filesha1("${path.module}/../docker/php/php.ini")
  }

  build {
    context    = "${path.module}/../docker/php"
    dockerfile = "Dockerfile"
  }
}

resource "docker_container" "php" {
  name  = "phparrot-php"
  image = docker_image.php.image_id
  user  = "1000:1000"

  log_driver = "json-file"
  log_opts   = local.log_rotativo

  networks_advanced {
    name = docker_network.phparrot_network.name
  }

  volumes {
    host_path      = local.project_path
    container_path = "/var/www/html"
  }

  dynamic "volumes" {
    for_each = local.ia ? [var.gateway_token_arquivo] : []

    content {
      host_path      = volumes.value
      container_path = local.gateway_token
      read_only      = true
    }
  }

  env = [
    "COMPOSER_MEMORY_LIMIT=-1",
    "COMPOSER_PROCESS_TIMEOUT=2000"
  ]
}

resource "docker_container" "agenda" {
  name    = "phparrot-agenda"
  image   = docker_image.php.image_id
  user    = "1000:1000"
  command = ["php", "artisan", "schedule:work"]
  restart = "unless-stopped"

  log_driver = "json-file"
  log_opts   = local.log_rotativo

  networks_advanced {
    name = docker_network.phparrot_network.name
  }

  volumes {
    host_path      = local.project_path
    container_path = "/var/www/html"
  }

  dynamic "volumes" {
    for_each = local.ia ? [var.gateway_token_arquivo] : []

    content {
      host_path      = volumes.value
      container_path = local.gateway_token
      read_only      = true
    }
  }

  depends_on = [
    docker_container.php
  ]
}

resource "docker_image" "nginx" {
  name = "phparrot-nginx"

  triggers = {
    dockerfile = filesha1("${path.module}/../docker/nginx/Dockerfile")
  }

  build {
    context    = "${path.module}/../docker/nginx"
    dockerfile = "Dockerfile"
  }
}

resource "docker_container" "nginx" {
  name  = "phparrot-nginx"
  image = docker_image.nginx.image_id

  log_driver = "json-file"
  log_opts   = local.log_rotativo

  networks_advanced {
    name = docker_network.phparrot_network.name
  }

  volumes {
    host_path      = abspath("${path.module}/../docker/nginx/default.conf")
    container_path = "/etc/nginx/conf.d/default.conf"
  }

  volumes {
    host_path      = local.project_path
    container_path = "/var/www/html"
  }

  ports {
    internal = 80
    external = var.app_port
    ip       = local.prod ? "127.0.0.1" : "0.0.0.0"
  }

  depends_on = [
    docker_container.php
  ]
}

resource "docker_image" "node" {
  count = local.prod ? 0 : 1
  name  = "node:24.22.2"
}

moved {
  from = docker_image.node
  to   = docker_image.node[0]
}

resource "docker_container" "node" {
  count       = local.prod ? 0 : 1
  name        = "phparrot-node"
  image       = docker_image.node[0].image_id
  user        = "1000:1000"
  working_dir = "/var/www/html"

  log_driver = "json-file"
  log_opts   = local.log_rotativo

  networks_advanced {
    name = docker_network.phparrot_network.name
  }

  volumes {
    host_path      = local.project_path
    container_path = "/var/www/html"
  }

  ports {
    internal = 5173
    external = var.vite_port
  }

  env = [
    "VITE_HOST_PORT=${var.vite_port}"
  ]

  tty = true
}

moved {
  from = docker_container.node
  to   = docker_container.node[0]
}

resource "docker_image" "whisper" {
  name = "phparrot-whisper"

  triggers = {
    dockerfile = filesha1("${path.module}/../docker/whisper/Dockerfile")
    iniciar    = filesha1("${path.module}/../docker/whisper/iniciar.sh")
    patch      = filesha1("${path.module}/../docker/whisper/patches/0001-apaga-temporario-na-falha-de-conversao.patch")
    modelo     = var.whisper_modelo
  }

  build {
    context    = "${path.module}/../docker/whisper"
    dockerfile = "Dockerfile"
    build_args = {
      MODELO      = var.whisper_modelo
      MODELO_SHA1 = local.whisper_sha1[var.whisper_modelo]
    }
  }
}

resource "docker_container" "whisper" {
  name        = "phparrot-whisper"
  image       = docker_image.whisper.image_id
  memory      = var.whisper_memoria
  memory_swap = var.whisper_memoria * 2
  log_driver  = "none"

  networks_advanced {
    name = docker_network.phparrot_network.name
  }

  tmpfs = {
    "/tmp" = "rw,noexec,nosuid,size=64m,uid=10001,gid=10001"
  }
}

resource "docker_image" "gateway" {
  count = local.ia ? 1 : 0
  name  = "phparrot-gateway"

  triggers = {
    codigo = sha1(join("\n", [for arquivo in local.gateway_arquivos : "${arquivo} ${filesha1("${local.gateway_contexto}/${arquivo}")}"]))
  }

  build {
    context    = local.gateway_contexto
    dockerfile = "Dockerfile"
  }
}

resource "docker_container" "gateway" {
  count       = local.ia ? 1 : 0
  name        = "phparrot-gateway"
  image       = docker_image.gateway[0].image_id
  user        = "10002:10002"
  memory      = var.gateway_memoria
  memory_swap = var.gateway_memoria * 2
  read_only   = true

  log_driver = "json-file"
  log_opts   = local.log_rotativo

  networks_advanced {
    name = docker_network.phparrot_network.name
  }

  host {
    host = "host.docker.internal"
    ip   = "host-gateway"
  }

  tmpfs = {
    "/tmp" = "rw,noexec,nosuid,size=16m,uid=10002,gid=10002"
  }

  volumes {
    host_path      = var.gateway_token_arquivo
    container_path = local.gateway_token
    read_only      = true
  }

  env = [
    "GATEWAY_OLLAMA_URL=${var.ollama_url}",
    "GATEWAY_MODELOS=llama3.2:3b",
    "GATEWAY_CONCORRENCIA=1",
    "GATEWAY_TOKEN_ARQUIVO=${local.gateway_token}",
  ]
}
