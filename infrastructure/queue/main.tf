resource "aws_sqs_queue" "publish_queue" {
  name                        = format("coverage-publish-%s.fifo", var.environment)
  fifo_queue                  = true
  content_based_deduplication = true
  visibility_timeout_seconds  = 120
  deduplication_scope         = "messageGroup"
  fifo_throughput_limit       = "perMessageGroupId"

  # The queue is going to spend a fair amount of time providing empty receives, so switching
  # to long polling will reduce the number of requests made to SQS.
  receive_wait_time_seconds = 20
}

resource "aws_sqs_queue" "webhooks_queue" {
  name                        = format("coverage-webhooks-%s.fifo", var.environment)
  fifo_queue                  = true
  content_based_deduplication = true
  visibility_timeout_seconds  = 120
  deduplication_scope         = "messageGroup"
  fifo_throughput_limit       = "perMessageGroupId"

  # The queue is going to spend a fair amount of time providing empty receives, so switching
  # to long polling will reduce the number of requests made to SQS.
  receive_wait_time_seconds = 20
}