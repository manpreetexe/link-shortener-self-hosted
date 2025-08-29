variable "project_id" {}
variable "region" {
  default = "us-central1"
}
variable "zone" {
  default = "us-central1-b"
}
variable "machine_type" {
  default = "e2-medium"
}
variable "disk_size_gb" {
  default = 50
}
variable "vm_name" {
  default = "your-domain-vm"
}
