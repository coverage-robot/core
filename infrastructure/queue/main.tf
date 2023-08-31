resource "aws_sqs_queue" "publish_queue" {
  name                        = format("coverage-publish-%s.fifo", var.environment)
  fifo_queue                  = true
  content_based_deduplication = true
  visibility_timeout_seconds  = 120
  deduplication_scope         = "messageGroup"
  fifo_throughput_limit       = "perMessageGroupId"
}