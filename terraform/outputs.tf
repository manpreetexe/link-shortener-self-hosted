output "static_ip" {
  value = google_compute_address.static_ip.address
}

output "instance_name" {
  value = google_compute_instance.docker_vm.name
}
