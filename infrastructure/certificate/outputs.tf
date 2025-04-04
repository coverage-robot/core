output "acm_certificate" {
  value     = aws_acm_certificate.certificate
  sensitive = true
}