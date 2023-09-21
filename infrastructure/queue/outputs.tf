output "publish_queue" {
  value = aws_sqs_queue.publish_queue
}

output "webhooks_queue" {
  value = aws_sqs_queue.webhooks_queue
}