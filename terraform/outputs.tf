output "app_url" {
  value = local.prod ? "http://127.0.0.1:${var.app_port}" : "http://localhost:${var.app_port}"
}

output "vite_url" {
  value = local.prod ? null : "http://localhost:${var.vite_port}"
}

output "gateway_imagem" {
  value = !local.ia ? null : (var.gateway_imagem == null ? "docker/gateway/Dockerfile: ${local.gateway_origem}" : "${var.gateway_imagem} (gateway_imagem, fora do Dockerfile)")
}
