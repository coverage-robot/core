resource "aws_s3_bucket" "terraform_state" {
  bucket = "tf-coverage-state"

  lifecycle {
    prevent_destroy = true
  }
}
