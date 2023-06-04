resource "google_bigquery_dataset" "environment_dataset" {
  dataset_id    = var.environment
  friendly_name = var.environment
  description   = "Dataset for ${var.environment} environment"

  labels = {
    environment = var.environment
  }
}

resource "google_bigquery_table" "line_coverage" {
  dataset_id          = google_bigquery_dataset.environment_dataset.dataset_id
  table_id            = "line_coverage"
  deletion_protection = false

  schema = <<EOF
[
  {
    "name": "uploadId",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The unique upload id for the uploaded file."
  },
  {
    "name": "commit",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The commit hash which the coverage was generated for."
  },
  {
    "name": "parent",
    "type": "STRING",
    "mode": "REPEATED",
    "description": "The parent commit hash(es) of the commit the coverage was generated for."
  },
  {
    "name": "sourceFormat",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The original format of the coverage file ingested."
  },
  {
    "name": "fileName",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The path of the file which the line exists in."
  },
  {
    "name": "lineNumber",
    "type": "INTEGER",
    "mode": "REQUIRED",
    "description": "The number of the line."
  },
  {
    "name": "type",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The type of the line coverage."
  },
  {
    "name": "tag",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The user provided tag of the coverage file."
  },
  {
    "name": "owner",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The repository owner for the VCS provider."
  },
  {
    "name": "repository",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The repository name for the VCS provider."
  },
  {
    "name": "provider",
    "type": "STRING",
    "mode": "REQUIRED",
    "description": "The VCS provider."
  },
  {
    "name": "metadata",
    "type": "RECORD",
    "mode": "REPEATED",
    "description": "The lines metadata.",
    "fields": [
        {
            "name": "key",
            "type": "STRING",
            "mode": "REQUIRED"
        },
        {
            "name": "value",
            "type": "STRING",
            "mode": "NULLABLE"
        }
    ]
  },
  {
    "name": "generatedAt",
    "type": "DATETIME",
    "mode": "NULLABLE",
    "description": "The time the coverage was generated."
  },
  {
    "name": "ingestTime",
    "type": "DATETIME",
    "mode": "NULLABLE",
    "description": "The number of the line."
  }
]
EOF
}