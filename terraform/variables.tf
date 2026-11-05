variable "ambiente" {
  type    = string
  default = "dev"

  validation {
    condition     = contains(["dev", "prod"], var.ambiente)
    error_message = "O ambiente precisa ser \"dev\" ou \"prod\"."
  }
}

variable "project_path" {
  type    = string
  default = null
}

variable "app_port" {
  type    = number
  default = 8090
}

variable "vite_port" {
  type    = number
  default = 5174
}

variable "whisper_modelo" {
  type    = string
  default = "base"
}

variable "whisper_memoria" {
  type    = number
  default = 1024
}

variable "gateway_memoria" {
  type    = number
  default = 256
}

variable "ollama_url" {
  type    = string
  default = "http://host.docker.internal:11434"
}

variable "gateway_token_arquivo" {
  type    = string
  default = null

  validation {
    condition     = var.gateway_token_arquivo == null ? true : startswith(var.gateway_token_arquivo, "/") && fileexists(var.gateway_token_arquivo)
    error_message = "O gateway_token_arquivo precisa ser um caminho absoluto de um arquivo que já existe."
  }
}
