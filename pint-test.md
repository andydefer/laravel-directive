# Pint Formatting Test Report
*Generated: mar. 16 juin 2026 01:26:09 WAT*


  ⨯.......................⨯..................⨯.⨯...⨯...⨯...⨯⨯...............⨯...⨯..............⨯....................⨯...⨯⨯⨯⨯.....⨯⨯⨯⨯⨯..⨯...⨯..⨯.......⨯⨯⨯.......⨯⨯⨯⨯⨯..⨯⨯..⨯⨯........
  ..........⨯...⨯⨯⨯...⨯⨯⨯.⨯⨯⨯...⨯....⨯......⨯......

  ──────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────────── Laravel  
    FAIL   ................................................................................................................................................ 229 files, 49 style issues  
  ⨯ src/Cli/CliRunner.php                                                                        class_attributes_separation, new_with_parentheses, function_declaration, concat_space  
  ⨯ src/Collections/ExecutionResultCollection.php                                                       function_declaration, unary_operator_spaces, not_operator_with_successor_space  
  ⨯ src/Collections/ReplacementCollection.php                                                                                                                             phpdoc_align  
  ⨯ src/Collections/SearchResultCollection.php                                                    new_with_parentheses, no_trailing_whitespace_in_comment, blank_line_before_statement  
  ⨯ src/Contexts/DirectiveContext.php                                                                   class_attributes_separation, new_with_parentheses, blank_line_before_statement  
  ⨯ src/Contexts/DirectiveTestingContext.php                                                                                         class_attributes_separation, new_with_parentheses  
  ⨯ src/Contexts/LaravelBootstrapperContext.php                                                                 concat_space, unary_operator_spaces, not_operator_with_successor_space  
  ⨯ src/Contracts/Services/FileSystemInterface.php                                                                                                     phpdoc_separation, phpdoc_align  
  ⨯ src/DirectiveServiceProvider.php                                                             new_with_parentheses, function_declaration, concat_space, blank_line_before_statement  
  ⨯ src/Enums/PermissionMode.php                                                             concat_space, unary_operator_spaces, phpdoc_separation, not_operator_with_successor_space  
  ⨯ src/Enums/PrimitiveType.php                                                                                                   no_trailing_whitespace_in_comment, phpdoc_separation  
  ⨯ src/Records/SearchResultRecord.php                                                                      no_trailing_whitespace_in_comment, braces_position, single_line_empty_body  
  ⨯ src/Services/DirectiveDiscoveryService.php                                                                   new_with_parentheses, concat_space, not_operator_with_successor_space  
  ⨯ src/Services/DirectiveExecutionService.php                                                 braces_position, no_unused_imports, single_line_empty_body, blank_line_before_statement  
  ⨯ src/Services/DirectiveHydratorService.php                                                                     class_attributes_separation, new_with_parentheses, no_unused_imports  
  ⨯ src/Services/DirectiveTestingService.php class_attributes_separation, new_with_parentheses, concat_space, not_operator_with_successor_space, blank_line_before_statement, phpdoc_…  
  ⨯ src/Services/FileCreatorService.php            concat_space, braces_position, not_operator_with_successor_space, single_line_empty_body, blank_line_before_statement, phpdoc_align  
  ⨯ src/Services/FileSystemService.php                                                 concat_space, phpdoc_separation, not_operator_with_successor_space, blank_line_before_statement  
  ⨯ src/Services/PathBuilderService.php                                               concat_space, braces_position, single_line_empty_body, blank_line_before_statement, phpdoc_align  
  ⨯ src/Services/PathSegmentsParserService.php new_with_parentheses, concat_space, braces_position, not_operator_with_successor_space, single_line_empty_body, blank_line_before_stat…  
  ⨯ src/Services/PrimitiveTypeConverterService.php                                 fully_qualified_strict_types, no_trailing_whitespace_in_comment, phpdoc_separation, ordered_imports  
  ⨯ src/Services/StringCaseConverterService.php                                               no_unused_imports, blank_line_after_namespace, blank_line_before_statement, phpdoc_align  
  ⨯ src/Strategies/SearchPathStrategy.php                                                                                        concat_space, braces_position, single_line_empty_body  
  ⨯ src/Testing/InteractsWithDirectives.php                            new_with_parentheses, concat_space, unary_operator_spaces, no_unused_imports, not_operator_with_successor_space  
  ⨯ src/Traits/FileCreator.php                                                                                  concat_space, unary_operator_spaces, not_operator_with_successor_space  
  ⨯ src/ValueObjects/SearchLimitVO.php                                                                                  class_attributes_separation, no_trailing_whitespace_in_comment  
  ⨯ src/ValueObjects/SearchSessionVO.php                                                                                class_attributes_separation, no_trailing_whitespace_in_comment  
  ⨯ tests/Feature/Cli/CliRunnerTest.php                                                class_attributes_separation, concat_space, no_unused_imports, not_operator_with_successor_space  
  ⨯ tests/Feature/DirectiveIntegrationTest.php                                                                                                                            concat_space  
  ⨯ tests/Feature/DirectiveServiceProviderTest.php                                                                                                                   no_unused_imports  
  ⨯ tests/Feature/LaravelDatabaseDirectiveTest.php                                                                                                                        concat_space  
  ⨯ tests/Feature/LaravelDatabaseIntegrationTest.php                                                                                                                      concat_space  
  ⨯ tests/Fixtures/Directives/TestConcreteDirective.php                                                                                                    class_attributes_separation  
  ⨯ tests/Fixtures/Directives/TestLaravelDatabaseDirective.php                                                  concat_space, unary_operator_spaces, not_operator_with_successor_space  
  ⨯ tests/IntegrationTestCase.php                                                                                                                      concat_space, no_unused_imports  
  ⨯ tests/Unit/AbstractDirectiveTest.php                                                                                             class_attributes_separation, new_with_parentheses  
  ⨯ tests/Unit/Collections/AbstractKeyValueCollectionTest.php                                                                                                          ordered_imports  
  ⨯ tests/Unit/Services/DirectiveDiscoveryServiceTest.php                                        class_attributes_separation, new_with_parentheses, function_declaration, concat_space  
  ⨯ tests/Unit/Services/DirectiveExecutionServiceTest.php                                                              class_attributes_separation, new_with_parentheses, concat_space  
  ⨯ tests/Unit/Services/DirectiveHydratorServiceTest.php                                                                             class_attributes_separation, new_with_parentheses  
  ⨯ tests/Unit/Services/DirectiveTestingServiceDatabaseTest.php                                                                                                           concat_space  
  ⨯ tests/Unit/Services/DirectiveTestingServiceTest.php                                         new_with_parentheses, fully_qualified_strict_types, no_unused_imports, ordered_imports  
  ⨯ tests/Unit/Services/FileCreatorServiceTest.php class_attributes_separation, new_with_parentheses, concat_space, unary_operator_spaces, no_unused_imports, not_operator_with_succe…  
  ⨯ tests/Unit/Services/FileSystemServiceTest.php class_attributes_separation, new_with_parentheses, concat_space, no_unused_imports, not_operator_with_successor_space, blank_line_b…  
  ⨯ tests/Unit/Services/PathBuilderServiceTest.php                                                                                   class_attributes_separation, new_with_parentheses  
  ⨯ tests/Unit/Services/PathSegmentsParserServiceTest.php                                                                                           new_with_parentheses, concat_space  
  ⨯ tests/Unit/Services/StringCaseConverterServiceTest.php                                                                                                        new_with_parentheses  
  ⨯ tests/Unit/Testing/ClosureDirectiveTest.php                                   class_attributes_separation, new_with_parentheses, function_declaration, blank_line_before_statement  
  ⨯ tests/Unit/Testing/TestDirectiveRegistryTest.php                                  class_attributes_separation, new_with_parentheses, fully_qualified_strict_types, ordered_imports  

