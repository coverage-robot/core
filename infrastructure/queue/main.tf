resource "aws_sqs_queue" "publish_queue" {
  name                        = format("coverage-publish-%s.fifo", var.environment)
  fifo_queue                  = true
  content_based_deduplication = true
  visibility_timeout_seconds  = 120
  deduplication_scope         = "messageGroup"
  fifo_throughput_limit       = "perMessageGroupId"
}

resource "aws_sqs_queue" "webhooks_queue" {
  name                        = format("coverage-webhooks-%s.fifo", var.environment)
  fifo_queue                  = true
  content_based_deduplication = true
  visibility_timeout_seconds  = 120
  deduplication_scope         = "messageGroup"
  fifo_throughput_limit       = "perMessageGroupId"

  # Add a small (5 second) delay between webhooks received from providers and analysis on
  # coverage starting. This gives a short period of time for providers to start more jobs
  # for the same commit. The queue is a first-in first-out, so the delay cannot be added
  # on a per-message basis.
  delay_seconds = 5
}