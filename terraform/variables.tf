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
