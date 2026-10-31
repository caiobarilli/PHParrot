locals {
  project_path = coalesce(var.project_path, abspath("${path.module}/.."))

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

  networks_advanced {
    name = docker_network.phparrot_network.name
  }

  volumes {
    host_path      = local.project_path
    container_path = "/var/www/html"
  }

  env = [
    "COMPOSER_MEMORY_LIMIT=-1",
    "COMPOSER_PROCESS_TIMEOUT=2000"
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
  }

  depends_on = [
    docker_container.php
  ]
}

resource "docker_image" "node" {
  name = "node:24.22.2"
}

resource "docker_container" "node" {
  name        = "phparrot-node"
  image       = docker_image.node.image_id
  user        = "1000:1000"
  working_dir = "/var/www/html"

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
